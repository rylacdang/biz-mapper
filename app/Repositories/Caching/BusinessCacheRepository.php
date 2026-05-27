<?php

namespace App\Repositories\Caching;

use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BusinessCacheRepository extends CacheBaseRepository implements BusinessRepositoryInterface
{
    public function __construct(
        BusinessRepositoryInterface $eloquentRepo
    ) {
        parent::__construct($eloquentRepo, 'businesses');
    }

    public function getSlugPatternCount(string $slug): int
    {
        return $this->eloquentRepo->getSlugPatternCount($slug);
    }

    public function getAllWithCategory(): Collection
    {
        return $this->remember(
            'all_with_category',
            fn() => $this->eloquentRepo->getAllWithCategory()
        );
    }

    public function getByUserIdWithCategory(int|string $userId): Collection
    {
        return $this->remember(
            "user.{$userId}.businesses",
            fn() => $this->eloquentRepo->getByUserIdWithCategory($userId)
        );
    }

    public function getByCategoryWithCategory(string $categoryName): Collection
    {
        return $this->remember(
            "category.{$categoryName}.businesses",
            fn() => $this->eloquentRepo->getByCategoryWithCategory($categoryName)
        );
    }
}
