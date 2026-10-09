<?php

use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\BusinessSearchController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('businesses/search', [BusinessSearchController::class, 'search'])->name('businesses.search');
Route::get('businesses/{slug}', BusinessProfileController::class)->name('businesses.show');

Route::middleware(['auth', 'verified'])->group(function () {
    // Redirection route instead of raw Inertia render
    Route::get('dashboard', function () {
        return redirect()->route(auth()->user()->role->value.'.dashboard');
    })->name('dashboard');

    require __DIR__.'/groups/owner.php';
    require __DIR__.'/groups/consumer.php';
});

require __DIR__.'/settings.php';
