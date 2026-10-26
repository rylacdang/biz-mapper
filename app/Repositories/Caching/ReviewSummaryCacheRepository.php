<?php

namespace App\Repositories\Caching;

use App\Models\Business;
use App\Models\ReviewSummary;
use App\Repositories\Contracts\ReviewSummaryRepositoryInterface;

class ReviewSummaryCacheRepository extends CacheBaseRepository implements ReviewSummaryRepositoryInterface
{
    public function __construct(ReviewSummaryRepositoryInterface $eloquentRepo)
    {
        parent::__construct($eloquentRepo, 'review_summaries');
    }

    public function forBusiness(int $businessId): ?ReviewSummary
    {
        return $this->remember(
            $this->key('forBusiness', [$businessId]),
            fn () => $this->eloquentRepo->forBusiness($businessId),
        );
    }

    public function lockBusiness(int $businessId): ?Business
    {
        return $this->eloquentRepo->lockBusiness($businessId);
    }

    public function snapshot(int $businessId, ?int $excludingUser = null): array
    {
        return $this->eloquentRepo->snapshot($businessId, $excludingUser);
    }

    public function businessIdsForUser(int $userId): array
    {
        return $this->eloquentRepo->businessIdsForUser($userId);
    }

    public function missingBusinessIds(): iterable
    {
        return $this->eloquentRepo->missingBusinessIds();
    }

    public function recoverableSummaries(bool $retryUnavailable = false): iterable
    {
        return $this->eloquentRepo->recoverableSummaries($retryUnavailable);
    }
}
