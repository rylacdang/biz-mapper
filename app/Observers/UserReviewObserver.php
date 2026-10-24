<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ReviewSummaryService;

class UserReviewObserver
{
    public function __construct(
        private readonly ReviewSummaryService $summaryService
    ) {}

    /**
     * Clear summaries before foreign-key cascades remove their sources.
     */
    public function deleting(User $user): void
    {
        $this->summaryService->invalidateForUser($user->id);
    }
}
