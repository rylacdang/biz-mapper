<?php

namespace App\Observers;

use App\Models\Review;
use App\Services\ReviewSummaryService;

class ReviewObserver
{
    public function __construct(
        private readonly ReviewSummaryService $summaryService
    ) {}

    /**
     * Invalidate the summary when a review is created.
     */
    public function created(Review $review): void
    {
        $this->summaryService->invalidate($review->business_id);
    }

    /**
     * Invalidate only when review content or eligibility changes.
     */
    public function updated(Review $review): void
    {
        if ($review->wasChanged(['body', 'rating', 'status', 'business_id', 'user_id'])) {
            $this->summaryService->invalidate($review->business_id);

            if ($review->wasChanged('business_id')) {
                $this->summaryService->invalidate((int) $review->getOriginal('business_id'));
            }
        }
    }

    /**
     * Remove the deleted review from the summary sources.
     */
    public function deleted(Review $review): void
    {
        $this->summaryService->invalidate($review->business_id);
    }
}
