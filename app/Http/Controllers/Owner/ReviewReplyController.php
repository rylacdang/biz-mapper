<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\SaveReviewReplyRequest;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ReviewReplyController extends Controller
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviewRepo
    ) {}

    /**
     * Create or update the owner's single reply.
     */
    public function update(SaveReviewReplyRequest $request, Business $business, Review $review): RedirectResponse
    {
        Gate::authorize('manage', [ReviewReply::class, $review]);

        $this->reviewRepo->saveReply($review->id, $request->user()->id, $request->validated('body'));

        return to_route('businesses.show', $business->slug);
    }

    /**
     * Remove an authorized owner reply.
     */
    public function destroy(Business $business, Review $review): RedirectResponse
    {
        Gate::authorize('manage', [ReviewReply::class, $review]);

        $this->reviewRepo->deleteReply($review->id);

        return to_route('businesses.show', $business->slug);
    }
}
