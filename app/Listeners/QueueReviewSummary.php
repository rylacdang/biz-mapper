<?php

namespace App\Listeners;

use App\Events\ReviewSummaryInvalidated;
use App\Services\ReviewSummaryService;

class QueueReviewSummary
{
    public function __construct(
        private readonly ReviewSummaryService $summaryService
    ) {}

    /**
     * Queue the current summary revision after commit.
     */
    public function handle(ReviewSummaryInvalidated $event): void
    {
        $this->summaryService->queue($event->businessId, $event->revision);
    }
}
