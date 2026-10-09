<?php

use App\Enums\UserRole;
use App\Http\Controllers\Owner\BusinessController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:'.UserRole::OWNER->value)->prefix('owner')->name('owner.')->group(function () {
    Route::post('businesses', [BusinessController::class, 'store'])->name('businesses.store');
    Route::put('businesses/{business}', [BusinessController::class, 'update'])->name('businesses.update');
    Route::delete('businesses/{business}', [BusinessController::class, 'destroy'])->name('businesses.destroy');
    Route::post('businesses/{id}/restore', [BusinessController::class, 'restore'])->name('businesses.restore');
});
