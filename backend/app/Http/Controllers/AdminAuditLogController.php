<?php

namespace App\Http\Controllers;

use App\Models\BranchAuditEntry;
use App\Models\School;
use App\Services\BranchAuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AdminAuditLogController extends Controller
{
    public function __invoke(Request $request, BranchAuditLog $audit): Response
    {
        $user = $request->user();
        abort_unless($user->canEnterSchoolhouse(), 403);
        $platform = $user->isSuperAdmin();
        $branches = $platform
            ? School::query()->orderBy('name')->get(['id', 'name'])
            : $audit->viewableBranches($user);

        abort_unless($platform || $branches->isNotEmpty(), 403);
        $filters = $request->validate([
            'branch' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', Rule::in(BranchAuditLog::ACTIONS)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);

        $branch = $filters['branch'] ?? null;
        abort_if($branch !== null && ! $branches->contains('id', $branch), 403);
        $entries = BranchAuditEntry::query()
            ->when(! $platform, fn ($query) => $query->whereIn('school_id', $branches->modelKeys()))
            ->when($branch, fn ($query) => $query->where('school_id', $branch))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('recorded_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('recorded_at', '<=', $to))
            ->orderByDesc('recorded_at')->orderByDesc('id')->paginate(25)->appends($filters);

        Inertia::setRootView('admin.app');

        return Inertia::render('Admin/AuditLog', [
            'entries' => $entries,
            'branches' => $branches,
            'filters' => $filters,
            'actions' => BranchAuditLog::ACTIONS,
            'adminName' => $user->name,
            'platform' => $platform,
            'canViewAuditLog' => true,
        ])->toResponse($request)->header('Cache-Control', 'no-store');
    }
}
