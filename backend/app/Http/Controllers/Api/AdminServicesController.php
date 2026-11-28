<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\PublicSchoolSiteController;
use App\Models\School;
use App\Services\BranchModules;
use App\Services\SchoolPageDocument;
use App\Services\SchoolWebsiteAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AdminServicesController extends AdminPlatformController
{
    public const RESOURCES = [
        'subscription-invoices' => 'subscription_invoices',
        'subscription-invoice-lines' => 'subscription_invoice_lines',
        'subscription-payments' => 'subscription_payments',
        'school-pages' => 'school_pages',
        'school-site-settings' => 'school_site_settings',
        'site-themes' => 'site_themes',
        'device-tokens' => 'device_tokens',
        'personal-access-tokens' => 'personal_access_tokens',
    ];

    public function index(Request $request, string $resource): JsonResponse
    {
        $fields = match ($resource) {
            'device-tokens' => ['id', 'user_id', 'platform', 'app_version', 'last_seen_at', 'created_at'],
            'personal-access-tokens' => ['id', 'tokenable_type', 'tokenable_id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'],
            default => null,
        };

        return $fields
            ? response()->json(DB::table(self::RESOURCES[$resource])->orderByDesc('id')->paginate(25, $fields))
            : parent::index($request, $resource);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        if ($resource !== 'personal-access-tokens') {
            return parent::store($request, $resource);
        }

        abort_unless($request->user('web')?->id === $request->user()->id, 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'days' => ['required', 'integer', 'between:1,30']]);
        $issued = $request->user()->createToken($data['name'], ['profile:read'], now()->addDays((int) $data['days']));

        return response()->json(['data' => $issued->accessToken, 'issued_token' => $issued->plainTextToken], 201)
            ->header('Cache-Control', 'no-store');
    }

    protected function records(Request $request, string $resource)
    {
        $query = parent::records($request, $resource);
        if ($this->isOwnerWebsiteRequest($resource)) {
            $query->whereIn('school_id', app(SchoolWebsiteAccess::class)->branchIds($request->user(), ! $request->isMethodSafe()));
        }

        return $query;
    }

    public function destroy(Request $request, string $resource, string $record): Response
    {
        abort_if($resource === 'school-site-settings' && $this->isOwnerWebsiteRequest($resource), 403);

        return parent::destroy($request, $resource, $record);
    }

    private function isOwnerWebsiteRequest(string $resource): bool
    {
        return in_array($resource, SchoolWebsiteAccess::RESOURCES, true) && Gate::denies('moderateListings', School::class);
    }

    public function options(string $resource): JsonResponse
    {
        $tables = match ($resource) {
            'subscription-invoices' => ['school_groups' => 'name'],
            'subscription-invoice-lines' => ['subscription_invoices' => 'invoice_no', 'schools' => 'name', 'modules' => 'name', 'plans' => 'name'],
            'subscription-payments' => ['subscription_invoices' => 'invoice_no'],
            'school-pages' => ['schools' => 'name'],
            'school-site-settings' => ['schools' => 'name', 'site_themes' => 'name'],
            'device-tokens' => ['users' => 'name'],
            default => [],
        };
        $options = [];
        $ownerWebsite = $this->isOwnerWebsiteRequest($resource);
        foreach ($tables as $table => $label) {
            $options[$table] = DB::table($table)->when($table === 'schools', fn ($query) => $query->whereNull('deleted_at'))
                ->when($ownerWebsite && $table === 'schools',
                    fn ($query) => $query->whereIn('id', app(SchoolWebsiteAccess::class)->branchIds(request()->user())))
                ->when($ownerWebsite && $table === 'site_themes', fn ($query) => $query->where('is_active', true))
                ->orderBy($label)->get(['id', $label.' as name']);
        }

        return response()->json(['data' => $options]);
    }

    public function previewPage(Request $request): JsonResponse
    {
        $rules = $this->rules('school-pages');
        $data = $request->validate([
            'school_id' => $rules['school_id'],
            'title' => ['nullable', 'string', 'max:150'],
            'content' => $rules['content'],
        ]);
        $branch = School::query()->findOrFail($data['school_id'], ['id', 'name', 'slug']);
        abort_if($this->isOwnerWebsiteRequest('school-pages')
            && ! app(SchoolWebsiteAccess::class)->branchIds($request->user())->containsStrict($branch->id), 403);
        $content = $this->pageContent($data['content'] ?? null);
        $html = view('directory.school-site', [
            'branch' => $branch,
            'site' => DB::table('school_site_settings as settings')->join('site_themes as theme', 'theme.id', '=', 'settings.site_theme_id')
                ->where('settings.school_id', $branch->id)->where('theme.is_active', true)->first(['settings.*', 'theme.slug as theme_slug'])
                ?? (object) ['footer_text' => null],
            'page' => (object) ['title' => $data['title'] ?? 'Untitled page', 'seo_title' => null, 'seo_description' => null, 'slug' => ''],
            'navigation' => collect(),
            'blocks' => is_array($blocks = $content['blocks'] ?? $content) ? $blocks : [],
            'document' => $content['document'] ?? null,
        ])->render();

        return response()->json(['data' => ['html' => $html]])->header('Cache-Control', 'private, no-store');
    }

    private function pageContent(?string $value): array
    {
        $content = $value === null ? [] : json_decode($value, true);
        if (! is_array($content) || (array_key_exists('document', $content) && ! SchoolPageDocument::valid($content['document']))) {
            throw ValidationException::withMessages(['content' => 'Enter supported rich text. Links must use HTTP or HTTPS.']);
        }

        return $content;
    }

    protected function present(object $row, string $resource): object
    {
        if (in_array($resource, ['device-tokens', 'personal-access-tokens'], true)) {
            unset($row->token);
        }

        return $row;
    }

    protected function prepare(Request $request, string $resource, ?object $current, array $data): array
    {
        $values = $data + (array) $current;
        if ($this->isOwnerWebsiteRequest($resource)) {
            $school = (int) $values['school_id'];
            abort_if($current && $school !== (int) $current->school_id, 403);
            abort_unless(app(SchoolWebsiteAccess::class)->branchIds($request->user(), writing: true)->containsStrict($school), 403);
            if (isset($data['site_theme_id']) && (int) $data['site_theme_id'] !== (int) $current?->site_theme_id
                && ! DB::table('site_themes')->where('id', $data['site_theme_id'])->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['site_theme_id' => 'Choose an active theme.']);
            }
        }
        if ($resource === 'school-pages' && isset($data['content'])) {
            $this->pageContent($data['content']);
        }
        foreach (['homepage_sections', 'menu_items', 'settings_schema'] as $jsonField) {
            if (isset($data[$jsonField]) && ! is_array(json_decode($data[$jsonField], true))) {
                throw ValidationException::withMessages([$jsonField => 'Enter a JSON object or list.']);
            }
        }
        if ($resource === 'subscription-invoices') {
            if ($values['period_start'] > $values['period_end']) {
                throw ValidationException::withMessages(['period_end' => 'The billing period ends before it starts.']);
            }
            $invoiceId = $current?->id;
            $subtotal = $invoiceId ? $this->sumCents('subscription_invoice_lines', $invoiceId, 'amount') : 0;
            $paid = $invoiceId ? $this->sumCents('subscription_payments', $invoiceId, 'amount') : 0;
            abort_if($current && (int) $values['school_group_id'] !== (int) $current->school_group_id
                && DB::table('subscription_invoice_lines')->where('subscription_invoice_id', $invoiceId)->exists(),
                409, 'The organisation is fixed once an invoice has lines.');
            $discount = $this->cents($values['discount_total'] ?? '0');
            $total = $subtotal - $discount;
            abort_if($total < $paid, 422, 'The discount cannot exceed the subtotal or leave less than the recorded payments.');
            $status = $values['status'] ?? 'draft';
            abort_if($paid > 0 && in_array($status, ['draft', 'void'], true), 409, 'Remove the payments before drafting or voiding this invoice.');
            if (in_array($status, ['unpaid', 'paid'], true)) {
                abort_if($total <= 0, 422, 'Add charge lines before issuing an invoice.');
                $status = $paid === $total ? 'paid' : 'unpaid';
                $data['issued_at'] = $values['issued_at'] ?? now();
            }
            $data['subtotal'] = $this->decimal($subtotal);
            $data['total_amount'] = $this->decimal($total);
            $data['status'] = $status;
        }
        if (in_array($resource, ['subscription-invoice-lines', 'subscription-payments'], true)) {
            $invoice = $this->lockedInvoice($values['subscription_invoice_id']);
            abort_if($current && (int) $current->subscription_invoice_id !== (int) $invoice->id, 409, 'A billing record cannot be moved to another invoice.');
            if ($resource === 'subscription-invoice-lines') {
                abort_unless($invoice->status === 'draft', 409, 'Edit lines while the invoice is a draft.');
                if (isset($values['school_id'])) {
                    $branch = DB::table('schools')->where('id', $values['school_id'])->lockForUpdate()->firstOrFail();
                    abort_unless((int) $branch->group_id === (int) $invoice->school_group_id, 422, 'The branch must belong to the billed organisation.');
                }
                $amount = $this->cents($values['unit_price'] ?? '0') * ($values['quantity'] ?? 1);
                abort_if($amount > 999999999999, 422, 'The line amount is too large.');
                $data['amount'] = $this->decimal($amount);
            } else {
                abort_unless(in_array($invoice->status, ['unpaid', 'paid'], true), 409, 'Only issued invoices can receive payments.');
                $otherPayments = $this->sumCents('subscription_payments', $invoice->id, 'amount', $current?->id);
                abort_if($otherPayments + $this->cents($values['amount']) > $this->cents($invoice->total_amount),
                    422, 'The payment is larger than the outstanding balance.');
                if (! $current) {
                    $data['recorded_by_user_id'] = $request->user()->id;
                }
            }
        }
        if ($resource === 'school-site-settings' && ($values['site_enabled'] ?? false)) {
            $school = DB::table('schools')->where('id', $values['school_id'])->firstOrFail();
            $theme = DB::table('site_themes')->where('id', $values['site_theme_id'])->firstOrFail();
            $module = app(BranchModules::class)->forBranch($school->id)->firstWhere('slug', 'website');
            abort_unless($school->verified_at && $school->status === 'active' && ($module['enabled'] ?? false) && ! ($module['read_only'] ?? false)
                && $theme->is_active, 422,
                'A verified branch, active Website module and active theme are required.');
            $data['published_at'] = $values['published_at'] ?? now();
        }
        if ($resource === 'personal-access-tokens' && array_key_exists('expires_at', $data)
            && ($data['expires_at'] === null || ($current?->expires_at && $data['expires_at'] > $current->expires_at))) {
            throw ValidationException::withMessages(['expires_at' => 'A token expiration can only be shortened.']);
        }

        return $data;
    }

    protected function beforeDelete(string $resource, object $record): void
    {
        if ($resource === 'subscription-invoices') {
            abort_unless($record->status === 'draft', 409, 'Void an issued invoice to preserve its history.');
        }
        if ($resource === 'subscription-invoice-lines') {
            abort_unless($this->lockedInvoice($record->subscription_invoice_id)->status === 'draft', 409, 'Edit lines while the invoice is a draft.');
        }
        if ($resource === 'subscription-payments') {
            $this->lockedInvoice($record->subscription_invoice_id);
        }
    }

    protected function afterSave(string $resource, object $record, ?object $previous): void
    {
        $this->refreshInvoice($resource, $record);
    }

    protected function afterDelete(string $resource, object $record): void
    {
        $this->refreshInvoice($resource, $record);
    }

    private function refreshInvoice(string $resource, object $record): void
    {
        if (! in_array($resource, ['subscription-invoice-lines', 'subscription-payments'], true)) {
            return;
        }

        $invoice = $this->lockedInvoice($record->subscription_invoice_id);
        $subtotal = $this->sumCents('subscription_invoice_lines', $invoice->id, 'amount');
        $total = $subtotal - $this->cents($invoice->discount_total);
        abort_if($total < 0 || $subtotal > 999999999999, 422, 'The invoice subtotal must cover its discount and fit the amount limit.');
        $paid = $this->sumCents('subscription_payments', $invoice->id, 'amount');
        $status = in_array($invoice->status, ['unpaid', 'paid'], true) ? ($paid === $total ? 'paid' : 'unpaid') : $invoice->status;

        DB::table('subscription_invoices')->where('id', $invoice->id)->update([
            'subtotal' => $this->decimal($subtotal), 'total_amount' => $this->decimal($total), 'status' => $status, 'updated_at' => now(),
        ]);
    }

    private function lockedInvoice(int $invoice): object
    {
        return DB::table('subscription_invoices')->where('id', $invoice)->lockForUpdate()->firstOrFail();
    }

    private function sumCents(string $table, int $invoice, string $column, ?int $except = null): int
    {
        return DB::table($table)->where('subscription_invoice_id', $invoice)
            ->when($except !== null, fn ($query) => $query->where('id', '!=', $except))
            ->pluck($column)->sum(fn ($value) => $this->cents($value));
    }

    private function cents(string|int|float $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    private function decimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    protected function rules(string $resource): array
    {
        $id = fn (string $table, bool $required = true) => [$required ? 'required' : 'nullable', 'integer',
            $table === 'schools' ? Rule::exists($table, 'id')->whereNull('deleted_at') : Rule::exists($table, 'id')];
        $text = fn (int $size, bool $required = false) => [$required ? 'required' : 'nullable', 'string', "max:{$size}"];
        $slug = fn (int $size) => ['required', 'alpha_dash:ascii', "max:{$size}"];
        $date = ['required', 'date_format:Y-m-d'];
        $currency = ['decimal:0,2', 'between:0,9999999999.99'];
        $sort = ['integer', 'between:-32768,32767'];
        $json = ['nullable', 'json', 'max:100000'];
        $color = ['nullable', 'regex:/^#[a-f0-9]{6}$/i'];

        return match ($resource) {
            'subscription-invoices' => [
                'school_group_id' => $id('school_groups'), 'invoice_no' => $text(30, true),
                'period_start' => $date, 'period_end' => $date, 'due_date' => $date, 'discount_total' => $currency,
                'status' => [Rule::in(['draft', 'unpaid', 'paid', 'void'])],
            ],
            'subscription-invoice-lines' => [
                'subscription_invoice_id' => $id('subscription_invoices'), 'school_id' => $id('schools', false),
                'module_id' => $id('modules', false), 'plan_id' => $id('plans', false), 'description' => $text(200, true),
                'quantity' => ['integer', 'between:1,32767'], 'unit_price' => ['decimal:0,2', 'between:0,99999999.99'],
            ],
            'subscription-payments' => [
                'subscription_invoice_id' => $id('subscription_invoices'), 'amount' => ['required', 'decimal:0,2', 'between:0.01,9999999999.99'],
                'paid_on' => [...$date, 'before_or_equal:today'], 'reference' => $text(100),
                'method' => [Rule::in(['bank_transfer', 'jazzcash', 'easypaisa', 'cash', 'other'])],
            ],
            'school-pages' => [
                'school_id' => $id('schools'), 'title' => $text(150, true), 'slug' => $slug(150),
                'page_type' => [Rule::in(['home', 'about', 'admissions', 'contact', 'custom'])], 'content' => $json,
                'is_published' => ['boolean'], 'show_in_menu' => ['boolean'], 'sort_order' => $sort,
                'seo_title' => $text(160), 'seo_description' => $text(300),
            ],
            'school-site-settings' => [
                'school_id' => $id('schools'), 'site_theme_id' => $id('site_themes'), 'site_enabled' => ['boolean'],
                'subdomain' => ['nullable', 'string', 'max:63', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', Rule::notIn(PublicSchoolSiteController::RESERVED_SUBDOMAINS)],
                'primary_color' => $color, 'secondary_color' => $color,
                'font_family' => ['nullable', Rule::in(['Instrument Sans', 'Inter', 'DM Sans', 'Source Sans 3', 'system-ui'])], 'homepage_sections' => $json,
                'menu_items' => $json, 'footer_text' => $text(255), 'analytics_id' => $text(40),
            ],
            'site-themes' => [
                'name' => $text(100, true), 'slug' => $slug(100), 'description' => $text(255),
                'preview_path' => ['nullable', 'max:255', 'regex:~\A(?!/)(?!.*(?:^|/)\.\.(?:/|$))[a-zA-Z0-9_./-]+\z~'],
                'version' => $text(20, true), 'is_premium' => ['boolean'], 'is_active' => ['boolean'], 'settings_schema' => $json,
            ],
            'device-tokens' => [
                'user_id' => $id('users'), 'token' => $text(255, true), 'platform' => ['required', Rule::in(['android', 'ios'])],
                'app_version' => $text(20), 'last_seen_at' => ['nullable', 'date'],
            ],
            'personal-access-tokens' => ['name' => $text(255, true), 'expires_at' => ['nullable', 'date']],
        };
    }
}
