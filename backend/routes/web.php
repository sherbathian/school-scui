<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Api\AdminCommunityController;
use App\Http\Controllers\Api\AdminDirectoryController;
use App\Http\Controllers\Api\AdminPlatformController;
use App\Http\Controllers\Api\AdminServicesController;
use App\Http\Controllers\Api\PostedFamilyReceiptController;
use App\Http\Controllers\InstituteClaimController;
use App\Http\Controllers\PublicDirectoryController;
use App\Http\Controllers\PublicResourcesController;
use App\Http\Controllers\SchoolPagePreviewController;
use App\Http\Resources\AdminListingResource;
use App\Models\School;
use App\Services\BranchModules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [PublicDirectoryController::class, 'home'])->name('directory.home');
Route::get('/directory', [PublicDirectoryController::class, 'index'])->name('directory.index');
Route::get('/{category}/{citySlug?}/{areaSlug?}', [PublicDirectoryController::class, 'index'])
    ->whereIn('category', array_keys(PublicDirectoryController::TYPES))->name('directory.category');
Route::get('/institutions/{slug}', [PublicDirectoryController::class, 'show'])->name('directory.profile');
Route::post('/institutions/{slug}/enquire', [PublicDirectoryController::class, 'enquire'])
    ->middleware('throttle:6,1')->name('directory.enquire');
Route::get('/institutions/{slug}/logo', [PublicDirectoryController::class, 'logo'])->name('directory.logo');
Route::get('/institutions/{slug}/media/{media}', [PublicDirectoryController::class, 'media'])
    ->whereNumber('media')->name('directory.media');
Route::get('/{collection}', [PublicDirectoryController::class, 'collection'])
    ->whereIn('collection', ['saved', 'compare'])->name('directory.collection');
Route::post('/{collection}/{slug}', [PublicDirectoryController::class, 'toggle'])
    ->whereIn('collection', ['saved', 'compare'])->name('directory.toggle');
Route::get('/{resource}', [PublicResourcesController::class, 'index'])
    ->whereIn('resource', array_keys(PublicResourcesController::PAGES))->name('directory.resources');
Route::get('/{resource}/{record}', [PublicResourcesController::class, 'show'])
    ->whereIn('resource', array_keys(PublicResourcesController::PAGES))->name('directory.resource');
Route::get('/study-resources/{record}/download', [PublicResourcesController::class, 'download'])
    ->whereNumber('record')->middleware('throttle:30,1')->name('directory.study-download');
Route::post('/books/{book}/enquire', [PublicResourcesController::class, 'enquireBook'])
    ->whereNumber('book')->middleware(['auth', 'throttle:8,1'])->name('directory.book-enquiry');
Route::get('/short-courses/{record?}', fn (?string $record = null) => redirect(
    $record ? route('directory.resource', ['courses', $record]) : route('directory.resources', 'courses')
));
Route::redirect('/saved-schools', '/saved');
Route::redirect('/guides', '/parent-guides');
Route::redirect('/about-taleem-finder', '/about');
Route::redirect('/find-my-school', '/directory');
Route::view('/for-schools', 'directory.for-schools')->name('directory.for-schools');
Route::get('/{topic}', [PublicResourcesController::class, 'policy'])
    ->whereIn('topic', ['community-privacy', 'terms-of-use', 'copyright-takedown'])->name('directory.policy');
Route::get('/sitemap.xml', [PublicDirectoryController::class, 'sitemap'])->name('directory.sitemap');
Route::get('/local/school-preview/{school}/{page?}', SchoolPagePreviewController::class)
    ->where(['school' => '[a-z0-9-]+', 'page' => '[a-z0-9_-]+'])->name('local.school-page-preview');
Route::view('/about', 'directory.about')->name('directory.about');
Route::view('/contact', 'directory.contact')->name('directory.contact');

Route::get('/signup', [InstituteClaimController::class, 'registration'])->name('directory.sign-up');
Route::post('/signup', [InstituteClaimController::class, 'register'])->middleware('throttle:6,1')->name('directory.sign-up.submit');
Route::get('/login', [InstituteClaimController::class, 'signInPage'])->name('directory.sign-in');
Route::post('/login', [InstituteClaimController::class, 'login'])->middleware('throttle:6,1')->name('directory.sign-in.submit');
Route::get('/dashboard', function (Request $request) {
    if ($request->user()?->canEnterSchoolhouse()) {
        return redirect()->route('admin.dashboard');
    }

    return $request->user() ? response()->view('directory.dashboard')->header('Cache-Control', 'private, no-store')
        : redirect()->route('directory.sign-in');
})->name('directory.dashboard');
Route::redirect('/add-school', '/for-schools');
Route::get('/institute/register', [InstituteClaimController::class, 'registration'])->name('institute.register');
Route::post('/institute/register', [InstituteClaimController::class, 'register'])->middleware('throttle:6,1');
Route::get('/institute/sign-in', [InstituteClaimController::class, 'signInPage'])->name('login');
Route::post('/institute/sign-in', [InstituteClaimController::class, 'login'])->middleware('throttle:6,1');
Route::get('/institute/new', [InstituteClaimController::class, 'submissions'])->middleware('auth')->name('institute.new');
Route::post('/institute/new', [InstituteClaimController::class, 'submit'])->middleware(['auth', 'throttle:6,1']);
Route::post('/institute/sign-out', [InstituteClaimController::class, 'logout'])->middleware('auth')->name('institute.logout');
Route::get('/institutions/{slug}/claim', [InstituteClaimController::class, 'show'])->name('institute.claim');
Route::post('/institutions/{slug}/claim', [InstituteClaimController::class, 'store'])->middleware(['auth', 'throttle:10,1']);
Route::post('/institutions/{slug}/claim/code', [InstituteClaimController::class, 'sendCode'])
    ->middleware(['auth', 'throttle:5,10,claim-send'])->name('institute.claim.send-code');
Route::post('/institutions/{slug}/claim/verify', [InstituteClaimController::class, 'verifyCode'])
    ->middleware(['auth', 'throttle:10,10,claim-verify'])->name('institute.claim.verify-code');

Route::get('/admin/claims', function (Request $request) {
    if (! Auth::check()) {
        return redirect()->route('admin.login');
    }

    Gate::authorize('moderateListings', School::class);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Claims', ['adminName' => $request->user()->name])
        ->toResponse($request)->header('Cache-Control', 'no-store');
})->name('admin.claims');

Route::get('/admin/branches/{branch}/modules', function (Request $request, int $branch) {
    if (! Auth::check()) {
        return redirect()->route('admin.login');
    }

    Gate::authorize('moderateListings', School::class);
    $school = School::query()->findOrFail($branch, ['id', 'name']);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/BranchModules', [
        'branch' => $school,
        'adminName' => $request->user()->name,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
})->whereNumber('branch')->name('admin.branches.modules');

$adminPage = function (Request $request, ?int $listing = null) {
    $administrator = Auth::check();
    $screen = match ($request->route()->getName()) {
        'admin.listings.create' => 'create',
        'admin.listings.edit' => 'edit',
        'admin.listings.show' => 'details',
        default => 'index',
    };

    if ($administrator) {
        if ($request->routeIs('admin.login') && $request->user()->canEnterSchoolhouse()) {
            return redirect()->route('admin.dashboard');
        }

        Gate::authorize('moderateListings', School::class);
    } elseif ($screen !== 'index') {
        return redirect()->route('admin.login');
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

Route::get('/admin/dashboard/widgets/{key}', \App\Http\Controllers\AdminDashboardWidgetController::class)
    ->middleware('auth')->name('admin.dashboard.widget');

Route::get('/admin/dashboard/{branch?}', AdminDashboardController::class)
    ->middleware('auth')->whereNumber('branch')->name('admin.dashboard');

Route::middleware('auth')->group(function (): void {
    Route::get('/admin/audit-log', \App\Http\Controllers\AdminAuditLogController::class)->name('admin.audit-log');

    Route::get('/admin/reports', \App\Http\Controllers\AdminReportController::class)->name('admin.reports.index');
    Route::middleware(\App\Http\Middleware\RequireBranchModule::class.':reports')->group(function (): void {
        Route::get('/admin/reports/{school}/all', \App\Http\Controllers\AdminReportController::class)
            ->whereNumber('school')->name('admin.reports.all');
        Route::get('/admin/reports/{school}/all/csv', \App\Http\Controllers\AdminReportController::class)
            ->whereNumber('school')->name('admin.reports.all.csv');
        Route::get('/admin/reports/{school}/all/print', \App\Http\Controllers\AdminReportController::class)
            ->whereNumber('school')->name('admin.reports.all.print');
        Route::get('/admin/reports/{school}', \App\Http\Controllers\AdminReportController::class)
            ->whereNumber('school')->name('admin.reports.branch');
        Route::get('/admin/reports/{school}/csv', \App\Http\Controllers\AdminReportController::class)
            ->whereNumber('school')->name('admin.reports.csv');
        Route::get('/admin/reports/{school}/print', \App\Http\Controllers\AdminReportController::class)
            ->whereNumber('school')->name('admin.reports.print');
    });
});

Route::get('/admin/login', $adminPage)->name('admin.login');
Route::get('/admin/listings', $adminPage)->name('admin.listings');
Route::get('/admin/listings/create', $adminPage)->name('admin.listings.create');
Route::get('/admin/listings/{listing}', $adminPage)->whereNumber('listing')->name('admin.listings.show');
Route::get('/admin/listings/{listing}/edit', $adminPage)->whereNumber('listing')->name('admin.listings.edit');

Route::get('/admin/{kind}', function (Request $request, string $kind) {
    if (! Auth::check()) {
        return redirect()->route('admin.login');
    }

    Gate::authorize('moderateListings', School::class);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Locations', [
        'kind' => $kind,
        'adminName' => $request->user()->name,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
})->whereIn('kind', ['cities', 'areas'])->name('admin.locations');

Route::get('/admin/directory/{resource}', function (Request $request, string $resource) {
    if (! Auth::check()) {
        return redirect()->route('admin.login');
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

Route::get('/admin/platform/{resource}', function (Request $request, string $resource) {
    if (! Auth::check()) {
        return redirect()->route('admin.login');
    }
    Gate::authorize('moderateListings', School::class);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Directory', [
        'resource' => $resource,
        'schoolId' => $request->integer('school_id') ?: null,
        'adminName' => $request->user()->name,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
})->whereIn('resource', array_keys(AdminPlatformController::RESOURCES));

Route::get('/admin/services/{resource}', function (Request $request, string $resource) {
    if (! Auth::check()) {
        return redirect()->route('admin.login');
    }
    Gate::authorize('moderateListings', School::class);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Directory', [
        'resource' => $resource,
        'adminName' => $request->user()->name,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
})->whereIn('resource', array_keys(AdminServicesController::RESOURCES));

Route::get('/admin/community/{resource}', function (Request $request, string $resource) {
    if (! Auth::check()) {
        return redirect()->route('admin.login');
    }
    Gate::authorize('moderateListings', School::class);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Directory', [
        'resource' => $resource,
        'adminName' => $request->user()->name,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
})->whereIn('resource', array_keys(AdminCommunityController::RESOURCES));

$accessPage = function (Request $request, string $screen, ?int $branch = null) {
    if (! Auth::check()) {
        return redirect()->route('admin.login');
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

Route::get('/admin/{screen}', $accessPage)->whereIn('screen', ['users', 'permissions', 'roles', 'members', 'operations']);
Route::get('/admin/branches/{branch}/{screen}', fn (Request $request, int $branch, string $screen) => $accessPage($request, $screen, $branch))->whereNumber('branch')->whereIn('screen', ['roles', 'members', 'operations']);
