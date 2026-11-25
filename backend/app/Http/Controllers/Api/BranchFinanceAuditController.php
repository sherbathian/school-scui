<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BranchFinanceAvailability;
use App\Services\BranchModules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BranchFinanceAuditController extends Controller
{
    private const array ACTIONS = ['receipt.posted', 'receipt.reversed', 'period.opened', 'period.closed'];

    public function index(Request $request, int $school, BranchModules $modules): JsonResponse
    {
        $user = $request->user();
        $branch = $user->activeSchools()->whereKey($school)->first();
        abort_unless($branch, 404);
        abort_unless($user->schoolAccessRole($branch) !== 'student' && $user->checkPermissionTo('fees.audit.view', 'web'), 403);
        $modules->assertEnabled($school, 'fees');

        abort_unless(app(BranchFinanceAvailability::class)->allows($school), 403);

        $filters = $request->validate([
            'action' => ['sometimes', Rule::in(self::ACTIONS)],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
        ]);

        $receipts = DB::table('family_receipt_finance_events')->where('school_id', $school)
            ->whereIn('action', ['receipt.posted', 'receipt.reversed'])
            ->selectRaw("'receipt' as source, id, school_id, actor_id, action,
                coalesce(accounting_recorded_at_utc, recorded_at) as event_time,
                case when accounting_recorded_at_utc is null then 1 else 0 end as legacy_timestamp,
                period_id, accounting_date, accounting_timezone as timezone,
                receipt_id, reversal_id, reason_code, currency, amount_minor, balances, allocation_ids,
                null as month, null as starts_on, null as ends_on, null as from_state, null as to_state");
        $periods = DB::table('branch_accounting_period_events')->where('school_id', $school)
            ->whereIn('action', ['period.opened', 'period.closed'])
            ->selectRaw("'period' as source, id, school_id, actor_id, action,
                recorded_at_utc as event_time, 0 as legacy_timestamp, period_id, business_date as accounting_date, timezone,
                null as receipt_id, null as reversal_id, null as reason_code, null as currency, null as amount_minor,
                null as balances, null as allocation_ids, month, starts_on, ends_on, from_state, to_state");

        $page = DB::query()->fromSub($receipts->unionAll($periods), 'finance_events')
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when(isset($filters['from']) || isset($filters['to']), fn ($query) => $query->where('legacy_timestamp', 0))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('event_time', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('event_time', '<=', $to))
            ->orderByDesc('event_time')->orderByDesc('source')->orderByDesc('id')
            ->simplePaginate((int) ($filters['per_page'] ?? 25))->appends($filters);

        return response()->json([
            'data' => $page->getCollection()->map($this->event(...)),
            'links' => ['previous' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
        ])->header('Cache-Control', 'no-store, private');
    }

    private function event(object $row): array
    {
        $event = [
            'id' => (int) $row->id, 'source' => $row->source,
            'school_id' => (int) $row->school_id, 'actor_id' => (int) $row->actor_id, 'action' => $row->action,
            'recorded_at' => $row->event_time, 'timestamp_basis' => (int) $row->legacy_timestamp ? 'legacy_recorded_at' : 'utc',
            'period_id' => $row->period_id === null ? null : (int) $row->period_id,
            'accounting_date' => $row->accounting_date, 'timezone' => $row->timezone,
        ];

        if ($row->source === 'period') {
            return $event + [
                'month' => $row->month, 'starts_on' => $row->starts_on, 'ends_on' => $row->ends_on,
                'from_state' => $row->from_state, 'to_state' => $row->to_state,
            ];
        }

        $balances = collect(json_decode($row->balances, true))->filter(fn ($line): bool =>
            is_array($line) && is_int($line['charge_id'] ?? null)
            && is_int($line['before_minor'] ?? null) && is_int($line['after_minor'] ?? null))
            ->map(fn (array $line): array => [
                'charge_id' => $line['charge_id'], 'before_minor' => $line['before_minor'], 'after_minor' => $line['after_minor'],
            ])->values();
        $allocations = collect(json_decode($row->allocation_ids, true))->filter(fn ($id): bool => is_int($id))->values();

        return $event + [
            'receipt_id' => (int) $row->receipt_id, 'reversal_id' => $row->reversal_id === null ? null : (int) $row->reversal_id,
            'reason_code' => $row->reason_code, 'currency' => $row->currency, 'amount_minor' => (int) $row->amount_minor,
            'balances' => $balances, 'allocation_ids' => $allocations,
        ];
    }
}
