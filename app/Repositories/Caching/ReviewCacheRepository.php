<?php

namespace App\Repositories\Caching;

use App\Models\Review;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReviewCacheRepository extends CacheBaseRepository implements ReviewRepositoryInterface
{
    protected array $relatedTags = ['notifications'];

    public function __construct(ReviewRepositoryInterface $eloquentRepo)
    {
        parent::__construct($eloquentRepo, 'reviews');
    }

    public function historyForUser(int $userId, int $page = 1): LengthAwarePaginator
    {
        return $this->remember(
            $this->key('historyForUser', [$userId, $page]),
            fn () => $this->eloquentRepo->historyForUser($userId, $page),
        );
    }

    public function forBusiness(int $businessId, int $page = 1): LengthAwarePaginator
    {
        return $this->remember(
            $this->key('forBusiness', [$businessId, $page]),
            fn () => $this->eloquentRepo->forBusiness($businessId, $page),
        );
    }

    public function summary(int $businessId): array
    {
        return $this->remember(
            $this->key('summary', [$businessId]),
            fn () => $this->eloquentRepo->summary($businessId),
        );
    }

    public function forUser(int $businessId, int $userId): ?Review
    {
        return $this->eloquentRepo->forUser($businessId, $userId);
    }

    public function saveReply(int $reviewId, int $userId, string $body): void
    {
        $this->eloquentRepo->saveReply($reviewId, $userId, $body);

        $this->invalidate();
    }

    public function deleteReply(int $reviewId): void
    {
        $this->eloquentRepo->deleteReply($reviewId);

        $this->invalidate();
    }

    public function pageForReview(int $businessId, int $reviewId): int
    {
        return $this->eloquentRepo->pageForReview($businessId, $reviewId);
    }

    public function lockBusiness(int $businessId): void
    {
        $this->eloquentRepo->lockBusiness($businessId);
    }
}
