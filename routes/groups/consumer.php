<?php

use App\Enums\UserRole;
use Illuminate\Support\Facades\Route;

Route::middleware('role:'.UserRole::CONSUMER->value)->prefix('consumer')->name('consumer.')
    ->group(function () {

    });

