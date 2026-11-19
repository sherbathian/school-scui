<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AdminPlatformController extends Controller
{
    public const RESOURCES = [
        'organisation-members' => 'school_group_user',
        'modules' => 'modules',
        'module-dependencies' => 'module_dependencies',
        'plans' => 'plans',
        'plan-modules' => 'plan_modules',
        'plan-limits' => 'plan_limits',
        'subscriptions' => 'school_subscriptions',
        'subscription-modules' => 'subscription_modules',
        'subscription-limits' => 'subscription_limits',
        'demo-requests' => 'demo_requests',
        'otp-codes' => 'otp_codes',
        'notifications' => 'notifications',
    ];

    protected const PAIRS = [
        'organisation-members' => ['school_group_id', 'user_id'],
        'module-dependencies' => ['module_id', 'requires_module_id'],
        'plan-modules' => ['plan_id', 'module_id'],
    ];

    private const BUILT_INS = ['listing', 'academics', 'students', 'inquiries', 'guardians', 'attendance', 'staff', 'fees', 'expenses'];

    public function index(Request $request, string $resource): JsonResponse
    {
        $query = $this->records($request, $resource);
        if ($resource === 'otp-codes') {
            $query->select(['id', 'user_id', 'channel', 'purpose', 'attempts', 'expires_at', 'consumed_at', 'created_at']);
        }
        if (in_array($resource, ['events', 'listing-leads', 'reviews', 'saved-schools', 'school-stats', 'student-fee-discounts', 'study-resources', 'school-pages', 'school-site-settings'], true)
            && $request->filled('school_id')) {
            $query->where('school_id', $request->integer('school_id'));
        }
        if ($resource === 'student-transfers' && $request->filled('branch_id')) {
            $branch = $request->integer('branch_id');
            $query->where(fn ($scope) => $scope->where('from_school_id', $branch)->orWhere('to_school_id', $branch));
        }
        $results = $query->orderByDesc(static::PAIRS[$resource][0] ?? 'id')->paginate(25);
        $results->through(fn (object $row) => $this->present($row, $resource));

        return response()->json($results);
    }

    public function inbox(Request $request): JsonResponse
    {
        return $this->index($request, 'notifications');
    }

    public function updateInbox(Request $request, string $record): JsonResponse
    {
        return $this->update($request, 'notifications', $record);
    }

    public function deleteInbox(Request $request, string $record): Response
    {
        return $this->destroy($request, 'notifications', $record);
    }

    public function options(string $resource): JsonResponse
    {
        $tables = match ($resource) {
            'organisation-members' => ['school_groups', 'users'],
            'module-dependencies' => ['modules'],
            'plan-modules' => ['plans', 'modules'],
            'plan-limits' => ['plans'],
            'subscriptions' => ['school_groups', 'plans'],
            'subscription-modules' => ['school_subscriptions', 'modules', 'users'],
            'subscription-limits' => ['school_subscriptions'],
            'demo-requests' => ['school_groups', 'users'],
            'notifications' => ['users'],
            default => [],
        };
        $options = [];
        foreach ($tables as $table) {
            $options[$table] = $table === 'school_subscriptions'
                ? DB::table($table)->join('school_groups', 'school_groups.id', '=', 'school_subscriptions.school_group_id')
                    ->orderBy('school_groups.name')->orderByDesc('school_subscriptions.id')
                    ->get(['school_subscriptions.id', 'school_groups.name'])->map(fn ($row) => [
                        'id' => $row->id, 'name' => $row->name.' #'.$row->id,
                    ])
                : DB::table($table)->orderBy('name')->get(['id', 'name']);
        }

        return response()->json(['data' => $options]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        return $this->save($request, $resource);
    }

    public function update(Request $request, string $resource, string $record): JsonResponse
    {
        return $this->save($request, $resource, $record);
    }

    public function destroy(Request $request, string $resource, string $record): Response
    {
        abort_if($resource === 'otp-codes', 405);
        try {
            DB::transaction(function () use ($request, $resource, $record): void {
                $query = $this->record($request, $resource, $record);
                $current = (clone $query)->lockForUpdate()->firstOrFail();
                abort_if($resource === 'modules' && in_array($current->slug, self::BUILT_INS, true), 409, 'Built-in modules cannot be deleted.');
                if ($resource === 'subscriptions') {
                    abort_if(Carbon::parse($current->starts_at)->lte(now()) || $current->payment_reference, 409, 'Cancel a started or paid subscription to preserve its history.');
                }
                if ($resource === 'organisation-members') {
                    $this->checkOwnerRemoval($current, []);
                }
                $this->beforeDelete($resource, $current);
                $query->delete();
                $this->afterDelete($resource, $current);
            }, 3);
        } catch (QueryException $error) {
            $this->handleIntegrityError($error);
        }

        return response()->noContent();
    }

    private function save(Request $request, string $resource, ?string $id = null): JsonResponse
    {
        if ($resource === 'otp-codes') {
            abort_unless($id !== null, 405);
            $request->validate(['invalidate' => ['required', 'accepted']]);
            $this->record($request, $resource, $id)->firstOrFail(['id']);
            $this->record($request, $resource, $id)->whereNull('consumed_at')->update(['consumed_at' => now(), 'updated_at' => now()]);

            return response()->json(['data' => $this->record($request, $resource, $id)->firstOrFail(['id', 'channel', 'purpose', 'attempts', 'expires_at', 'consumed_at'])]);
        }

        $rules = $this->rules($resource);
        if ($id !== null) {
            $rules = array_map(fn (array $rule) => ['sometimes', ...$rule], $rules);
        }
        $data = $request->validate($rules);
        foreach (['starts_at', 'ends_at', 'grace_ends_at', 'published_at', 'expires_at', 'last_seen_at'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = Carbon::parse($data[$field])->format('Y-m-d H:i:s');
            }
        }

        try {
            $row = DB::transaction(function () use ($request, $resource, $id, $data): object {
                $query = $id === null ? null : $this->record($request, $resource, $id);
                $current = $query ? (clone $query)->lockForUpdate()->firstOrFail() : null;
                if ($resource === 'notifications') {
                    return $this->saveNotification($request, $query, $data);
                }
                if (in_array($resource, ['module-dependencies', 'modules', 'plan-modules'], true)) {
                    DB::table('modules')->orderBy('id')->lockForUpdate()->pluck('id');
                }
                if ($resource === 'plans') {
                    DB::table('plans')->orderBy('id')->lockForUpdate()->pluck('id');
                }
                $data = $this->prepare($request, $resource, $current, $data);

                if (! in_array($resource, ['module-dependencies', 'plan-modules', 'saved-schools'], true)) {
                    $data['updated_at'] = now();
                }
                if ($query === null && $resource !== 'module-dependencies') {
                    $data['created_at'] = now();
                }

                $table = static::RESOURCES[$resource];
                if (isset(static::PAIRS[$resource])) {
                    $pair = array_intersect_key($data + (array) $current, array_flip(static::PAIRS[$resource]));
                    if ($query === null) {
                        DB::table($table)->insert($data);
                    } elseif ($data !== []) {
                        $query->update($data);
                    }

                    return DB::table($table)->where($pair)->firstOrFail();
                }
                if ($query) {
                    $query->update($data);
                } else {
                    $id = DB::table($table)->insertGetId($data);
                }

                $saved = DB::table($table)->where('id', $id)->firstOrFail();
                $this->afterSave($resource, $saved, $current);

                return $saved;
            }, 3);
        } catch (QueryException $error) {
            $this->handleIntegrityError($error);
        }

        return response()->json(['data' => $this->present($row, $resource)], $id === null ? 201 : 200);
    }

    private function records(Request $request, string $resource)
    {
        $query = DB::table(static::RESOURCES[$resource]);
        if ($resource === 'notifications') {
            $query->where('notifiable_type', $request->user()->getMorphClass())->where('notifiable_id', $request->user()->id);
        }

        return $query;
    }

    private function record(Request $request, string $resource, string $id)
    {
        $query = $this->records($request, $resource);
        if (isset(static::PAIRS[$resource])) {
            abort_unless(preg_match('/^\d+-\d+$/D', $id), 404);

            return $query->where(array_combine(static::PAIRS[$resource], explode('-', $id)));
        }
        abort_unless($resource === 'notifications' ? Str::isUuid($id) : ctype_digit($id), 404);

        return $query->where('id', $id);
    }

    protected function present(object $row, string $resource): object
    {
        if (isset(static::PAIRS[$resource])) {
            [$left, $right] = static::PAIRS[$resource];
            $row->id = $row->$left.'-'.$row->$right;
        }
        if ($resource === 'modules') {
            $row->applies_to = json_decode($row->applies_to ?? 'null', true);
            $row->protected = in_array($row->slug, self::BUILT_INS, true);
        }
        if ($resource === 'notifications') {
            $payload = json_decode($row->data, true);
            $row->title = $payload['title'] ?? $row->type;
            $row->message = $payload['message'] ?? '';
            $row->read = $row->read_at !== null;
            unset($row->data);
        }

        return $row;
    }

    private function saveNotification(Request $request, $query, array $data): object
    {
        if ($query) {
            abort_unless(array_key_exists('read', $data), 422);
            $query->update(['read_at' => $data['read'] ? now() : null, 'updated_at' => now()]);

            return $query->firstOrFail();
        }
        $recipient = User::query()->findOrFail($data['notifiable_id']);
        $id = (string) Str::uuid();
        $recipient->notifications()->create([
            'id' => $id, 'type' => 'platform.notice', 'data' => [
                'title' => $data['title'], 'message' => $data['message'], 'sender_id' => $request->user()->id,
            ],
        ]);

        return DB::table('notifications')->where('id', $id)->firstOrFail();
    }

    protected function beforeDelete(string $resource, object $record): void {}

    protected function afterDelete(string $resource, object $record): void {}

    protected function afterSave(string $resource, object $record, ?object $previous): void {}

    protected function prepare(Request $request, string $resource, ?object $current, array $data): array
    {
        $merged = $data + (array) $current;
        if ($resource === 'modules') {
            $types = $merged['applies_to'] ?? null;
            $types = is_string($types) ? json_decode($types, true) : $types;
            if ($current && in_array($current->slug, self::BUILT_INS, true)) {
                abort_if($merged['slug'] !== $current->slug, 409, 'Built-in module slugs are fixed.');
            }
            abort_if(($merged['is_core'] ?? false) && $merged['slug'] !== 'listing', 422, 'Only the free listing is core.');
            abort_if($merged['slug'] === 'listing' && (! ($merged['is_core'] ?? false) || ! ($merged['is_active'] ?? true) || ! empty($types)), 409, 'The free listing must stay available to every institute type.');
            if (array_key_exists('applies_to', $data) && $data['applies_to'] !== null) {
                $data['applies_to'] = json_encode($data['applies_to'], JSON_THROW_ON_ERROR);
            }
        }
        if ($resource === 'module-dependencies') {
            $this->checkDependency((int) $merged['module_id'], (int) $merged['requires_module_id'], $current);
        }
        if ($resource === 'organisation-members') {
            $this->checkOwnerRemoval($current, $merged);
            DB::table('school_groups')->where('id', $merged['school_group_id'])->lockForUpdate()->firstOrFail();
            if ($merged['role'] === 'owner' && ($merged['is_active'] ?? true)) {
                abort_if(DB::table('school_group_user')->where('school_group_id', $merged['school_group_id'])
                    ->where('role', 'owner')->where('is_active', true)->where('user_id', '!=', $merged['user_id'])->exists(),
                    409, 'This organisation already has an active owner.');
            }
        }
        if ($resource === 'plans' && ($merged['is_default'] ?? false)) {
            abort_unless((bool) ($merged['is_active'] ?? true), 422, 'The default plan must be active.');
            DB::table('plans')->where('is_default', true)->update(['is_default' => false, 'updated_at' => now()]);
        }
        if ($resource === 'subscriptions' && $current && Carbon::parse($current->starts_at)->lte(now())) {
            abort_if((int) $merged['school_group_id'] !== (int) $current->school_group_id
                || $merged['starts_at'] !== $current->starts_at, 409, 'A started subscription cannot be moved or rescheduled.');
        }
        if (in_array($resource, ['subscriptions', 'subscription-modules'], true)) {
            foreach (['ends_at', 'grace_ends_at'] as $field) {
                $minimum = $field === 'grace_ends_at' ? ($merged['ends_at'] ?? null) : $merged['starts_at'];
                if (isset($merged[$field]) && ($minimum === null || $merged[$field] <= $minimum)) {
                    throw ValidationException::withMessages([$field => 'Enter an end date after the preceding date.']);
                }
            }
        }
        if ($resource === 'subscription-modules' && $current === null) {
            $data['granted_by_user_id'] = $request->user()->id;
        }

        return $data;
    }

    private function checkOwnerRemoval(?object $current, array $next): void
    {
        if ($current?->role !== 'owner' || ! $current->is_active) {
            return;
        }
        if (($next['role'] ?? null) === 'owner' && ($next['is_active'] ?? false)
            && (int) $next['school_group_id'] === (int) $current->school_group_id
            && (int) $next['user_id'] === (int) $current->user_id) {
            return;
        }
        abort_if(DB::table('schools')->where('group_id', $current->school_group_id)->where('claim_status', 'claimed')->exists(), 409,
            'The owner of a claimed organisation is protected.');
    }

    private function checkDependency(int $module, int $requirement, ?object $current): void
    {
        abort_if(DB::table('modules')->where('id', $module)->where('is_core', true)->exists(), 422, 'The core listing cannot depend on another module.');
        $edges = DB::table('module_dependencies')->get()->reject(fn ($edge) => $current
            && $edge->module_id === $current->module_id && $edge->requires_module_id === $current->requires_module_id)->groupBy('module_id');
        $pending = [$requirement];
        $visited = [];
        while ($pending) {
            $node = array_pop($pending);
            abort_if($module === (int) $node, 422, 'This dependency would create a cycle.');
            if (! isset($visited[$node])) {
                $visited[$node] = true;
                foreach ($edges->get($node, []) as $edge) {
                    $pending[] = $edge->requires_module_id;
                }
            }
        }
    }

    private function handleIntegrityError(QueryException $error): never
    {
        if (in_array($error->errorInfo[0] ?? null, ['23000', '23503', '23505'], true)) {
            abort(409, 'This record already exists or is referenced by another record.');
        }
        throw $error;
    }

    protected function rules(string $resource): array
    {
        $fk = fn (string $table, bool $required = true) => [$required ? 'required' : 'nullable', 'integer', Rule::exists($table, 'id')];
        $slug = fn (int $max) => ['required', 'alpha_dash:ascii', "max:{$max}"];
        $text = fn (int $max, bool $required = false) => [$required ? 'required' : 'nullable', 'string', "max:{$max}"];
        $limit = ['nullable', 'integer', 'between:0,2147483647'];
        $types = ['school', 'college', 'university', 'training_institute'];
        $sort = ['integer', 'between:-32768,32767'];

        return match ($resource) {
            'organisation-members' => [
                'school_group_id' => $fk('school_groups'), 'user_id' => $fk('users'),
                'role' => ['required', Rule::in(['owner', 'group_admin', 'group_accountant', 'group_viewer'])], 'is_active' => ['boolean'],
            ],
            'modules' => [
                'slug' => $slug(50), 'name' => $text(100, true), 'description' => $text(255),
                'module_group' => $text(30, true), 'applies_to' => ['nullable', 'list', 'max:4'],
                'applies_to.*' => ['distinct', Rule::in($types)], 'is_core' => ['boolean'], 'is_active' => ['boolean'], 'sort_order' => $sort,
            ],
            'module-dependencies' => ['module_id' => $fk('modules'), 'requires_module_id' => $fk('modules')],
            'plans' => [
                'name' => $text(100, true), 'slug' => $slug(100), 'price_monthly' => ['decimal:0,2', 'between:0,99999999.99'],
                'is_default' => ['boolean'], 'is_active' => ['boolean'], 'sort_order' => $sort, 'description' => $text(255),
            ],
            'plan-modules' => ['plan_id' => $fk('plans'), 'module_id' => $fk('modules')],
            'plan-limits' => ['plan_id' => $fk('plans'), 'limit_key' => $slug(50), 'limit_value' => $limit],
            'subscriptions' => [
                'school_group_id' => $fk('school_groups'), 'plan_id' => $fk('plans'),
                'status' => [Rule::in(['trial', 'active', 'grace', 'expired', 'cancelled'])],
                'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date'], 'grace_ends_at' => ['nullable', 'date'],
                'payment_reference' => $text(100),
            ],
            'subscription-limits' => [
                'school_subscription_id' => $fk('school_subscriptions'),
                'limit_key' => $slug(50), 'limit_value' => $limit, 'reason' => $text(255),
            ],
            'subscription-modules' => [
                'school_subscription_id' => $fk('school_subscriptions'), 'module_id' => $fk('modules'),
                'source' => [Rule::in(['addon', 'trial', 'manual_grant'])],
                'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date'], 'note' => $text(255),
            ],
            'demo-requests' => [
                'name' => $text(150, true), 'institute_name' => $text(150), 'institute_type' => ['nullable', Rule::in($types)],
                'phone' => $text(30, true), 'email' => ['nullable', 'email', 'max:190'], 'city' => $text(100),
                'branches' => ['nullable', 'integer', 'between:0,32767'], 'students_estimate' => $limit,
                'message' => $text(16000), 'source_site' => $text(30, true),
                'status' => [Rule::in(['new', 'contacted', 'trial', 'converted', 'lost'])],
                'assigned_to_user_id' => $fk('users', false), 'school_group_id' => $fk('school_groups', false),
            ],
            'notifications' => [
                'notifiable_id' => $fk('users'), 'title' => $text(150, true), 'message' => $text(2000, true), 'read' => ['boolean'],
            ],
        };
    }
}
