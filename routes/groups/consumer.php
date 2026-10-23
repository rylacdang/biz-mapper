<?php

use App\Enums\UserRole;
use App\Http\Controllers\Consumer\SavedBusinessController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:'.UserRole::CONSUMER->value)->prefix('consumer')->name('consumer.')->group(function () {
    Route::get('saved-places', [SavedBusinessController::class, 'index'])->name('saved-places.index');
    Route::post('saved-places/{business}', [SavedBusinessController::class, 'store'])->name('saved-places.store');
    Route::delete('saved-places/{business}', [SavedBusinessController::class, 'destroy'])->withTrashed()->name('saved-places.destroy');
});
