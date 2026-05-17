<?php

use App\Enums\UserRole;
use Illuminate\Support\Facades\Route;

Route::middleware('role:'.UserRole::OWNER->value)->prefix('owner')->name('owner.')
    ->group(function () {

    });
