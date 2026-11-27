<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Api\AdminCommunityController;
use App\Http\Controllers\Api\AdminDirectoryController;
use App\Http\Controllers\Api\AdminPlatformController;
use App\Http\Controllers\Api\AdminServicesController;
use App\Http\Controllers\Api\PostedFamilyReceiptController;
use App\Http\Resources\AdminListingResource;
use App\Models\School;
use App\Services\BranchModules;
use App\Services\SchoolWebsiteAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

$loginUrl = fn (Request $request) => route(
    $request->route()->getDomain() === 'admin.taleemfinder.pk' ? 'management.admin.login' : 'admin.login'
);

Route::get('/claims', function (Request $request) use ($loginUrl) {
    if (! Auth::check()) {
        return redirect()->to($loginUrl($request));
    }

    Gate::authorize('moderateListings', School::class);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Claims', ['adminName' => $request->user()->name])
        ->toResponse($request)->header('Cache-Control', 'no-store');
})->name('admin.claims');

Route::get('/branches/{branch}/modules', function (Request $request, int $branch) use ($loginUrl) {
    if (! Auth::check()) {
        return redirect()->to($loginUrl($request));
    }

    Gate::authorize('moderateListings', School::class);
    $school = School::query()->findOrFail($branch, ['id', 'name']);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/BranchModules', [
        'branch' => $school,
        'adminName' => $request->user()->name,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
})->whereNumber('branch')->name('admin.branches.modules');

$adminPage = function (Request $request, ?int $listing = null) use ($loginUrl) {
    $administrator = Auth::check();
    $screen = match (Str::after($request->route()->getName(), 'management.')) {
        'admin.listings.create' => 'create',
        'admin.listings.edit' => 'edit',
        'admin.listings.show' => 'details',
        default => 'index',
    };

    if ($administrator) {
        if ($request->routeIs('admin.login', 'management.admin.login') && $request->user()->canEnterSchoolhouse()) {
            return redirect()->route($request->routeIs('management.admin.login') ? 'management.admin.dashboard' : 'admin.dashboard');
        }

        Gate::authorize('moderateListings', School::class);
    } elseif ($screen !== 'index') {
        return redirect()->to($loginUrl($request));
    }

    $record = $listing === null ? null : School::query()->findOrFail($listing);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Listings', [
        'administrator' => $administrator,
        'adminName' => $request->user()?->name,
        'screen' => $screen,
        'listing' => $record ? (new AdminListingResource($record))->resolve($request) : null,
        'locationNames' => $screen === 'details' ? [
            'city' => DB::table('cities')->where('id', $record->city_id)->value('name'),
            'area' => DB::table('areas')->where('id', $record->area_id)->value('name'),
        ] : null,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
};

Route::get('/dashboard/widgets/{key}', \App\Http\Controllers\AdminDashboardWidgetController::class)
    ->middleware('auth')->name('admin.dashboard.widget');

Route::get('/dashboard/{branch?}', AdminDashboardController::class)
    ->middleware('auth')->whereNumber('branch')->name('admin.dashboard');

Route::middleware('auth')->group(function (): void {
    Route::get('/audit-log', \App\Http\Controllers\AdminAuditLogController::class)->name('admin.audit-log');

    Route::get('/reports', \App\Http\Controllers\AdminReportController::class)->name('admin.reports.index');
    Route::middleware(\App\Http\Middleware\RequireBranchModule::class.':reports')->group(function (): void {
        Route::get('/reports/{school}/all', \App\Http\Controllers\AdminReportController::class)
            ->whereNumber('school')->name('admin.reports.all');
        Route::get('/reports/{school}/all/csv', \App\Http\Controllers\AdminReportController::class)
            ->whereNumber('school')->name('admin.reports.all.csv');
        Route::get('/reports/{school}/all/print', \App\Http\Controllers\AdminReportController::class)
            ->whereNumber('school')->name('admin.reports.all.print');
        Route::get('/reports/{school}', \App\Http\Controllers\AdminReportController::class)
            ->whereNumber('school')->name('admin.reports.branch');
        Route::get('/reports/{school}/csv', \App\Http\Controllers\AdminReportController::class)
            ->whereNumber('school')->name('admin.reports.csv');
        Route::get('/reports/{school}/print', \App\Http\Controllers\AdminReportController::class)
            ->whereNumber('school')->name('admin.reports.print');
    });
});

Route::get('/login', $adminPage)->name('admin.login');
Route::get('/listings', $adminPage)->name('admin.listings');
Route::get('/listings/create', $adminPage)->name('admin.listings.create');
Route::get('/listings/{listing}', $adminPage)->whereNumber('listing')->name('admin.listings.show');
Route::get('/listings/{listing}/edit', $adminPage)->whereNumber('listing')->name('admin.listings.edit');

Route::get('/{kind}', function (Request $request, string $kind) use ($loginUrl) {
    if (! Auth::check()) {
        return redirect()->to($loginUrl($request));
    }

    Gate::authorize('moderateListings', School::class);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Locations', [
        'kind' => $kind,
        'adminName' => $request->user()->name,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
})->whereIn('kind', ['cities', 'areas'])->name('admin.locations');

Route::get('/directory/{resource}', function (Request $request, string $resource) use ($loginUrl) {
    if (! Auth::check()) {
        return redirect()->to($loginUrl($request));
    }
    Gate::authorize('moderateListings', School::class);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Directory', [
        'resource' => $resource,
        'schoolId' => $request->integer('school_id') ?: null,
        'branches' => $resource === 'listing-assignments' ? School::query()->orderBy('name')->get(['id', 'name']) : [],
        'adminName' => $request->user()->name,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
})->whereIn('resource', [...array_keys(AdminDirectoryController::RESOURCES), 'listing-assignments']);

Route::get('/platform/{resource}', function (Request $request, string $resource) use ($loginUrl) {
    if (! Auth::check()) {
        return redirect()->to($loginUrl($request));
    }
    Gate::authorize('moderateListings', School::class);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Directory', [
        'resource' => $resource,
        'schoolId' => $request->integer('school_id') ?: null,
        'adminName' => $request->user()->name,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
})->whereIn('resource', array_keys(AdminPlatformController::RESOURCES));

Route::get('/services/{resource}', function (Request $request, string $resource) use ($loginUrl) {
    if (! Auth::check()) {
        return redirect()->to($loginUrl($request));
    }
    $platform = Gate::allows('moderateListings', School::class);
    abort_unless($platform || (in_array($resource, SchoolWebsiteAccess::RESOURCES, true)
        && app(SchoolWebsiteAccess::class)->branchIds($request->user())->isNotEmpty()), 403);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Directory', [
        'resource' => $resource,
        'platform' => $platform,
        'adminName' => $request->user()->name,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
})->whereIn('resource', array_keys(AdminServicesController::RESOURCES));

Route::get('/community/{resource}', function (Request $request, string $resource) use ($loginUrl) {
    if (! Auth::check()) {
        return redirect()->to($loginUrl($request));
    }
    Gate::authorize('moderateListings', School::class);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Directory', [
        'resource' => $resource,
        'adminName' => $request->user()->name,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
})->whereIn('resource', array_keys(AdminCommunityController::RESOURCES));

$accessPage = function (Request $request, string $screen, ?int $branch = null) use ($loginUrl) {
    if (! Auth::check()) {
        return redirect()->to($loginUrl($request));
    }

    $user = $request->user();
    $platform = Gate::allows('moderateListings', School::class);
    abort_unless($platform || (in_array($screen, ['operations', 'members', 'roles'], true) && $user->canEnterSchoolhouse()), 403);
    $branches = $platform ? School::query() : $user->activeSchools();
    $school = $branch !== null ? (clone $branches)->findOrFail($branch) : null;
    $accessibleBranches = ! $platform ? $branches->orderBy('name')->get() : collect();
    if (! $platform && $screen !== 'operations') {
        $permission = $screen === 'members' ? 'users.manage' : 'roles.manage';
        $eligibleBranches = $accessibleBranches->filter(fn (School $item) => $user->hasSchoolPermission($item, $permission));
        abort_if($eligibleBranches->isEmpty() || ($school && ! $eligibleBranches->contains($school)), 403);
        $school ??= $eligibleBranches->first();
        $branchOptions = $eligibleBranches->map->only('id', 'name')->values();
    } else {
        $branchOptions = $platform ? $branches->orderBy('name')->get(['id', 'name']) : $accessibleBranches->map->only('id', 'name');
        if (! $platform && $screen === 'operations' && $accessibleBranches->count() === 1) {
            $school ??= $accessibleBranches->first();
        }
    }
    $allowedModules = ! $platform && $school && $screen === 'operations' ? $user->schoolhouseOperationModules($school) : [];
    $financePilotGrants = $school && $screen === 'operations'
        ? app(PostedFamilyReceiptController::class)->workspaceGrants($user, $school, app(BranchModules::class)) : [];
    abort_if(! $platform && $school && $screen === 'operations' && $allowedModules === [] && $financePilotGrants === [], 403);
    $enabledModules = ! $platform && $school && $screen === 'operations' ? app(BranchModules::class)->forBranch($school->id)
        ->where('enabled', true)->pluck('slug')->all() : [];
    $schoolAccess = [];
    foreach (['members' => 'users.manage', 'roles' => 'roles.manage'] as $section => $permission) {
        if ($accessibleBranches->contains(fn (School $item) => $user->hasSchoolPermission($item, $permission))) {
            $schoolAccess[] = $section;
        }
    }
    Inertia::setRootView('admin.app');

    return Inertia::render($screen === 'operations' ? 'Admin/BranchWorkspace' : 'Admin/Access', [
        'screen' => $screen,
        'branch' => $school?->only('id', 'name'),
        'branches' => in_array($screen, ['users', 'members', 'roles', 'operations'], true)
            ? $branchOptions : [],
        'platform' => $platform,
        'schoolAccess' => $schoolAccess,
        'financePilotGrants' => $financePilotGrants,
        'operations' => $platform || $financePilotGrants !== [] || $accessibleBranches->contains(fn (School $item) => $user->schoolhouseOperationModules($item) !== []),
        'allowedModules' => array_values(array_intersect($enabledModules, $allowedModules)),
        'academicsEnabled' => in_array('academics', $enabledModules, true) && $user->hasSchoolPermission($school, 'academics.view'),
        'adminName' => $request->user()->name,
        'adminId' => $request->user()->id,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
};

Route::get('/{screen}', $accessPage)->whereIn('screen', ['users', 'permissions', 'roles', 'members', 'operations']);
Route::get('/branches/{branch}/{screen}', fn (Request $request, int $branch, string $screen) => $accessPage($request, $screen, $branch))->whereNumber('branch')->whereIn('screen', ['roles', 'members', 'operations']);
