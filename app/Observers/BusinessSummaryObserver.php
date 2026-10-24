<?php

namespace App\Observers;

use App\Models\Business;
use App\Services\ReviewSummaryService;

class BusinessSummaryObserver
{
    public function __construct(
        private readonly ReviewSummaryService $summaryService
    ) {}

    /**
     * Hide summaries when a business is deleted.
     */
    public function deleted(Business $business): void
    {
        $this->summaryService->invalidate($business->id);
    }

    /**
     * Recheck review eligibility when a business is restored.
     */
    public function restored(Business $business): void
    {
        $this->summaryService->invalidate($business->id);
    }
}
