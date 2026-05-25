<?php

use App\Enums\UserRole;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\OwnerDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:'.UserRole::OWNER->value)->prefix('owner')->name('owner.')
    ->group(function () {
        Route::get('/dashboard', [OwnerDashboardController::class, 'index'])->name('dashboard');

        Route::post('business', [BusinessController::class, 'store'])->name('business.store');
    });
