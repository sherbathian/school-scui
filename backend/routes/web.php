<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InstituteClaimController;
use App\Http\Controllers\PublicDirectoryController;
use App\Http\Controllers\PublicResourcesController;
use App\Http\Controllers\SchoolPagePreviewController;
use App\Http\Controllers\PublicSchoolSiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
Route::get('/school-sites/{school}/{page?}', PublicSchoolSiteController::class)
    ->where(['school' => '[a-z0-9-]+', 'page' => '[a-z0-9_-]+'])->name('school-site.page');
Route::get('/local/school-preview/{school}/{page?}', SchoolPagePreviewController::class)
    ->where(['school' => '[a-z0-9-]+', 'page' => '[a-z0-9_-]+'])->name('local.school-page-preview');
Route::view('/about', 'directory.about')->name('directory.about');
Route::view('/contact', 'directory.contact')->name('directory.contact');

Route::get('/signup', [InstituteClaimController::class, 'registration'])->name('directory.sign-up');
Route::post('/signup', [InstituteClaimController::class, 'register'])->middleware('throttle:6,1')->name('directory.sign-up.submit');
Route::get('/login', [InstituteClaimController::class, 'signInPage'])->name('directory.sign-in');
Route::post('/login', [InstituteClaimController::class, 'login'])->middleware('throttle:6,1')->name('directory.sign-in.submit');
Route::get('/dashboard', [InstituteClaimController::class, 'dashboard'])->name('directory.dashboard');
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

Route::domain('admin.taleemfinder.pk')->group(function (): void {
    $entry = fn (Request $request) => redirect()->route(
        $request->user()?->canEnterSchoolhouse() ? 'management.admin.dashboard' : 'management.admin.login'
    );

    Route::get('/', $entry);
    Route::get('/admin/login', $entry);
    Route::get('/admin/dashboard', $entry);
    Route::get('/admin/{path?}', function (Request $request, ?string $path = null) {
        $query = $request->server('QUERY_STRING');

        return redirect()->to('/'.ltrim($path ?? '', '/').($query ? '?'.$query : ''));
    })->where('path', '.*');
    Route::post('/login', [AuthController::class, 'adminLogin'])->middleware('throttle:6,1');

    Route::name('management.')->group(__DIR__.'/admin.php');
});

Route::prefix('admin')->group(__DIR__.'/admin.php');
