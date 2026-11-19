<?php

use App\Http\Controllers\InstituteClaimController;
use App\Http\Controllers\PublicDirectoryController;
use App\Http\Resources\AdminListingResource;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [PublicDirectoryController::class, 'home'])->name('directory.home');
Route::get('/directory', [PublicDirectoryController::class, 'index'])->name('directory.index');
Route::get('/{category}/{citySlug?}/{areaSlug?}', [PublicDirectoryController::class, 'index'])
    ->whereIn('category', array_keys(PublicDirectoryController::TYPES))->name('directory.category');
Route::get('/institutions/{slug}', [PublicDirectoryController::class, 'show'])->name('directory.profile');
Route::get('/{collection}', [PublicDirectoryController::class, 'collection'])
    ->whereIn('collection', ['saved', 'compare'])->name('directory.collection');
Route::post('/{collection}/{slug}', [PublicDirectoryController::class, 'toggle'])
    ->whereIn('collection', ['saved', 'compare'])->name('directory.toggle');
Route::get('/sitemap.xml', [PublicDirectoryController::class, 'sitemap'])->name('directory.sitemap');
Route::view('/about', 'directory.about')->name('directory.about');
Route::view('/contact', 'directory.contact')->name('directory.contact');

Route::get('/institute/register', [InstituteClaimController::class, 'registration'])->name('institute.register');
Route::post('/institute/register', [InstituteClaimController::class, 'register'])->middleware('throttle:6,1');
Route::get('/institute/sign-in', [InstituteClaimController::class, 'signInPage'])->name('login');
Route::post('/institute/sign-in', [InstituteClaimController::class, 'login'])->middleware('throttle:6,1');
Route::get('/institute/new', [InstituteClaimController::class, 'submissions'])->middleware('auth')->name('institute.new');
Route::post('/institute/new', [InstituteClaimController::class, 'submit'])->middleware(['auth', 'throttle:6,1']);
Route::post('/institute/sign-out', [InstituteClaimController::class, 'logout'])->middleware('auth')->name('institute.logout');
Route::get('/institutions/{slug}/claim', [InstituteClaimController::class, 'show'])->name('institute.claim');
Route::post('/institutions/{slug}/claim', [InstituteClaimController::class, 'store'])->middleware(['auth', 'throttle:10,1']);

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
        default => 'index',
    };

    if ($administrator) {
        Gate::authorize('moderateListings', School::class);

        if ($request->routeIs('admin.login')) {
            return redirect()->route('admin.listings');
        }
    } elseif ($screen !== 'index') {
        return redirect()->route('admin.login');
    }

    $record = $listing === null ? null : School::query()->findOrFail($listing);
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Listings', [
        'administrator' => $administrator,
        'adminName' => $request->user()?->name,
        'screen' => $screen,
        'listing' => $record?->only(AdminListingResource::PROFILE_FIELDS),
    ])->toResponse($request)->header('Cache-Control', 'no-store');
};

Route::get('/admin/login', $adminPage)->name('admin.login');
Route::get('/admin/listings', $adminPage)->name('admin.listings');
Route::get('/admin/listings/create', $adminPage)->name('admin.listings.create');
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

$accessPage = function (Request $request, string $screen, ?int $branch = null) {
    if (! Auth::check()) {
        return redirect()->route('admin.login');
    }

    Gate::authorize('moderateListings', School::class);
    $school = $branch !== null ? School::query()->findOrFail($branch, ['id', 'name']) : null;
    Inertia::setRootView('admin.app');

    return Inertia::render('Admin/Access', [
        'screen' => $screen,
        'branch' => $school,
        'branches' => in_array($screen, ['members', 'roles'], true)
            ? School::query()->orderBy('name')->get(['id', 'name']) : [],
        'adminName' => $request->user()->name,
        'adminId' => $request->user()->id,
    ])->toResponse($request)->header('Cache-Control', 'no-store');
};

Route::get('/admin/{screen}', $accessPage)->whereIn('screen', ['users', 'permissions', 'roles', 'members']);
Route::get('/admin/branches/{branch}/{screen}', fn (Request $request, int $branch, string $screen) => $accessPage($request, $screen, $branch))->whereNumber('branch')->whereIn('screen', ['roles', 'members']);
