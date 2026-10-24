<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\IndexReviewsRequest;
use App\Models\Business;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService
    ) {}

    /**
     * Return published feedback and the current viewer's review.
     */
    public function __invoke(IndexReviewsRequest $request, Business $business): JsonResponse
    {
        Gate::authorize('view', $business);

        return response()->json($this->reviewService->pageData($business, $request->user(), $request->integer('page', 1)))
            ->header('Cache-Control', 'private, no-store');
    }
}
