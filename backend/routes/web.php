<?php

use App\Http\Controllers\PublicDirectoryController;
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
        'listing' => $record?->only([
            'id', 'name', 'institute_type', 'city_id', 'area_id', 'short_name',
            'branch_name', 'tagline', 'address_line', 'phone', 'email', 'website_url', 'description',
        ]),
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
