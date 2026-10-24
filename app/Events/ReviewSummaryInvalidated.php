<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ReviewSummaryInvalidated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * Carry the business and revision that changed.
     */
    public function __construct(
        public int $businessId,
        public int $revision
    ) {}
}
