<?php

namespace App\Jobs;

use App\Services\ReviewSummaryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Queue\Middleware\WithoutOverlapping;

#[Backoff([60, 300])]
#[UniqueFor(120)]
#[Tries(3)]
#[Timeout(60)]
class GenerateBusinessSummary implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Keep the business revision and select an asynchronous connection.
     */
    public function __construct(public int $businessId, public int $revision)
    {
        $connection = config('ai.review_summaries.queue_connection');

        // Review submissions must never wait for a synchronous AI request.
        $this->onConnection(config("queue.connections.{$connection}.driver") === 'sync' ? 'database' : $connection);
    }

    /**
     * Use one unique queue lock per business.
     */
    public function uniqueId(): string
    {
        return (string) $this->businessId;
    }

    /**
     * Prevent two workers from summarizing the same business.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('review-summary:'.$this->businessId))
                ->releaseAfter(15)
                ->expireAfter(90)
        ];
    }

    /**
     * Generate only the current due summary revision.
     */
    public function handle(ReviewSummaryService $summaryService): void
    {
        $summaryService->generate($this->businessId, $this->revision);
    }
}
