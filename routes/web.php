<?php

use App\Http\Controllers\Business\DirectionsController;
use App\Http\Controllers\Business\OfferingController;
use App\Http\Controllers\Business\ReviewController;
use App\Http\Controllers\Business\ReviewSummaryController;
use App\Http\Controllers\Business\SearchController;
use App\Http\Controllers\Business\ShowController;
use App\Http\Controllers\Collection\ShowController as CollectionShowController;
use App\Http\Controllers\ExplorerController;
use Illuminate\Support\Facades\Route;

Route::get('/', ExplorerController::class)->name('explorer.index');
Route::get('collections/{shareToken}', CollectionShowController::class)->whereUuid('shareToken')->name('collections.show');

Route::get('businesses/search', SearchController::class)->name('businesses.search');
Route::get('businesses/{slug}/directions', DirectionsController::class)->middleware('throttle:30,1')->name('businesses.directions');
Route::get('businesses/{business:slug}/offerings', OfferingController::class)->name('businesses.offerings.index');
Route::get('businesses/{business:slug}/reviews', ReviewController::class)->name('businesses.reviews.index');
Route::get('businesses/{business:slug}/review-summary', ReviewSummaryController::class)->name('businesses.review-summary.show');
Route::get('businesses/{slug}', ShowController::class)->name('businesses.show');

Route::middleware(['auth', 'verified'])->group(function () {
    require __DIR__.'/groups/owner.php';
    require __DIR__.'/groups/consumer.php';
});

require __DIR__.'/settings.php';
