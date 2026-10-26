<?php

use App\Enums\UserRole;
use App\Http\Controllers\Consumer\CollectionController;
use App\Http\Controllers\Consumer\CollectionPlaceController;
use App\Http\Controllers\Consumer\CollectionSharingController;
use App\Http\Controllers\Consumer\ReviewController;
use App\Http\Controllers\Consumer\SavedBusinessController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:'.UserRole::CONSUMER->value)->prefix('consumer')->name('consumer.')->group(function () {
    Route::get('reviews', [ReviewController::class, 'index'])->middleware('throttle:60,1')->name('reviews.index');

    Route::post('businesses/{business}/reviews', [ReviewController::class, 'store'])->middleware('throttle:20,1')->name('reviews.store');
    Route::put('businesses/{business}/reviews/{review}', [ReviewController::class, 'update'])->scopeBindings()->name('reviews.update');
    Route::delete('businesses/{business}/reviews/{review}', [ReviewController::class, 'destroy'])->scopeBindings()->name('reviews.destroy');

    Route::get('saved-places', [SavedBusinessController::class, 'index'])->name('saved-places.index');
    Route::post('saved-places/{business}', [SavedBusinessController::class, 'store'])->name('saved-places.store');
    Route::delete('saved-places/{business}', [SavedBusinessController::class, 'destroy'])->withTrashed()->name('saved-places.destroy');

    Route::get('collections', [CollectionController::class, 'index'])->name('collections.index');
    Route::post('collections', [CollectionController::class, 'store'])->name('collections.store');
    Route::get('collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');
    Route::put('collections/{collection}', [CollectionController::class, 'update'])->name('collections.update');
    Route::delete('collections/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy');

    Route::put('collections/{collection}/sharing', CollectionSharingController::class)->name('collections.sharing.update');

    Route::get('collections/{collection}/saved-places', [CollectionPlaceController::class, 'index'])->name('collections.places.index');
    Route::post('collections/{collection}/places/{savedBusiness}', [CollectionPlaceController::class, 'store'])->name('collections.places.store');
    Route::put('collections/{collection}/places/{savedBusiness}/order', [CollectionPlaceController::class, 'update'])->name('collections.places.update');
    Route::delete('collections/{collection}/places/{savedBusiness}', [CollectionPlaceController::class, 'destroy'])->name('collections.places.destroy');
});
