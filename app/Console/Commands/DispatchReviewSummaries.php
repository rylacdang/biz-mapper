<?php

namespace App\Console\Commands;

use App\Services\ReviewSummaryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:summaries:dispatch {--retry : Retry unavailable summaries}')]
#[Description('Backfill and recover due business review summaries')]
class DispatchReviewSummaries extends Command
{
    /**
     * Backfill eligible businesses and recover due summaries.
     */
    public function handle(ReviewSummaryService $summaryService): int
    {
        $summaryService->dispatchDue((bool) $this->option('retry'));

        return self::SUCCESS;
    }
}
