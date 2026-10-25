<?php

namespace App\Http\Controllers\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\IndexReviewsRequest;
use App\Http\Requests\Review\SaveReviewRequest;
use App\Http\Resources\ReviewHistoryResource;
use App\Models\Business;
use App\Models\Review;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService,
        private readonly ReviewRepositoryInterface $reviewRepo,
    ) {}

    /**
     * List only the signed-in consumer's own reviews.
     */
    public function index(IndexReviewsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Review::class);

        return ReviewHistoryResource::collection(
            $this->reviewRepo->historyForUser($request->user()->id, (int) $request->validated('page', 1))
        )->response()->header('Cache-Control', 'private, no-store');
    }

    /**
     * Publish the consumer's first review for this business.
     */
    public function store(SaveReviewRequest $request, Business $business): RedirectResponse
    {
        Gate::authorize('create', [Review::class, $business]);

        $this->reviewService->create($business, $request->user(), $request->validated());

        return to_route('businesses.show', $business->slug);
    }

    /**
     * Update content without changing publication eligibility.
     */
    public function update(SaveReviewRequest $request, Business $business, Review $review): RedirectResponse
    {
        Gate::authorize('update', $review);

        $this->reviewService->update($review, $request->validated());

        return to_route('businesses.show', $business->slug);
    }

    /**
     * Remove the author's review and its reply.
     */
    public function destroy(Business $business, Review $review): RedirectResponse
    {
        Gate::authorize('delete', $review);

        $this->reviewService->delete($review);

        return to_route('businesses.show', $business->slug);
    }
}
