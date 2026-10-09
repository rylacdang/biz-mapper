<?php

namespace App\Repositories\Caching;

use App\Models\Business;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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

    public function findBySlugWithCategoryOrFail(string $slug): Business
    {
        return $this->eloquentRepo->findBySlugWithCategoryOrFail($slug);
    }

    public function getAllWithCategory(): Collection
    {
        return $this->remember(
            'all_with_category',
            fn () => $this->eloquentRepo->getAllWithCategory()
        );
    }

    public function getByUserIdWithCategory(int|string $userId): Collection
    {
        return $this->remember(
            "user.{$userId}.businesses",
            fn () => $this->eloquentRepo->getByUserIdWithCategory($userId)
        );
    }

    public function getByCategoryWithCategory(string $categoryName): Collection
    {
        return $this->remember(
            "category.{$categoryName}.businesses",
            fn () => $this->eloquentRepo->getByCategoryWithCategory($categoryName)
        );
    }

    public function getTrashedByUserIdWithCategory(int|string $userId): Collection
    {
        return $this->eloquentRepo->getTrashedByUserIdWithCategory($userId);
    }

    /**
     * Find a soft-deleted business or throw when missing.
     */
    public function findTrashedOrFail(int|string $id): Model
    {
        return $this->eloquentRepo->findTrashedOrFail($id);
    }

    public function restoreBusiness(int|string $id): bool
    {
        $restored = $this->eloquentRepo->restoreBusiness($id);

        if ($restored) {
            $this->flush();
        }

        return $restored;
    }

    public function searchByRadius(float $latitude, float $longitude, float $radius, ?string $keyword = null, ?string $category = null): Collection
    {
        return $this->eloquentRepo->searchByRadius($latitude, $longitude, $radius, $keyword, $category);
    }
}
