<?php

use App\Models\School;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/listings', function () {
    if (Auth::check()) {
        Gate::authorize('moderateListings', School::class);
    }

    return response()->view('admin.listings')->header('Cache-Control', 'no-store');
})->name('admin.listings');
