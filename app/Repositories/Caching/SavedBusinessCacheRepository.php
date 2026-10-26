<?php

namespace App\Repositories\Caching;

use App\Repositories\Contracts\SavedBusinessRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SavedBusinessCacheRepository extends CacheBaseRepository implements SavedBusinessRepositoryInterface
{
    protected array $relatedTags = ['place_collections'];

    public function __construct(SavedBusinessRepositoryInterface $eloquentRepo)
    {
        parent::__construct($eloquentRepo, 'saved_businesses');
    }

    public function forUser(int $userId, int $page = 1): LengthAwarePaginator
    {
        return $this->remember(
            $this->key('forUser', [$userId, $page]),
            fn () => $this->eloquentRepo->forUser($userId, $page),
        );
    }

    public function saveForUser(int $userId, int $businessId): void
    {
        $this->eloquentRepo->saveForUser($userId, $businessId);

        $this->invalidate();
    }

    public function removeForUser(int $userId, int $businessId): void
    {
        $this->eloquentRepo->removeForUser($userId, $businessId);

        $this->invalidate();
    }

    public function markSaved(Collection $businesses, ?int $userId): void
    {
        $this->eloquentRepo->markSaved($businesses, $userId);
    }
}
