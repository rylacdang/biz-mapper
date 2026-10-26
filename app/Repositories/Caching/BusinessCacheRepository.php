<?php

namespace App\Repositories\Caching;

use App\Models\Business;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class BusinessCacheRepository extends CacheBaseRepository implements BusinessRepositoryInterface
{
    protected array $relatedTags = ['saved_businesses', 'place_collections', 'notifications', 'reviews', 'business_offerings', 'review_summaries'];

    public function __construct(BusinessRepositoryInterface $eloquentRepo)
    {
        parent::__construct($eloquentRepo, 'businesses');
    }

    public function incrementProfileViews(int $businessId): bool
    {
        return $this->eloquentRepo->incrementProfileViews($businessId);
    }

    public function getOwnerInsights(int $ownerId, array $businessIds): Collection
    {
        return $this->eloquentRepo->getOwnerInsights($ownerId, $businessIds);
    }

    public function findBySlugWithCategoryOrFail(string $slug): Business
    {
        return $this->remember(
            $this->key('findBySlugWithCategoryOrFail', [$slug]),
            fn () => $this->eloquentRepo->findBySlugWithCategoryOrFail($slug),
        );
    }

    public function getSlugPatternCount(string $slug): int
    {
        return $this->eloquentRepo->getSlugPatternCount($slug);
    }

    public function getAllWithCategory(): Collection
    {
        return $this->remember(
            $this->key('getAllWithCategory', []),
            fn () => $this->eloquentRepo->getAllWithCategory(),
        );
    }

    public function paginateByUserIdWithCategory(int $userId, string $query = '', int $page = 1): LengthAwarePaginator
    {
        return $this->eloquentRepo->paginateByUserIdWithCategory($userId, $query, $page);
    }

    public function getByCategoryWithCategory(string $categoryName): Collection
    {
        return $this->remember(
            $this->key('getByCategoryWithCategory', [$categoryName]),
            fn () => $this->eloquentRepo->getByCategoryWithCategory($categoryName),
        );
    }

    public function getTrashedByUserIdWithCategory(int|string $userId): Collection
    {
        return $this->eloquentRepo->getTrashedByUserIdWithCategory($userId);
    }

    public function findTrashedOrFail(int|string $id): Model
    {
        return $this->eloquentRepo->findTrashedOrFail($id);
    }

    public function restoreBusiness(int|string $id): bool
    {
        $result = $this->eloquentRepo->restoreBusiness($id);

        if ($result) {
            $this->invalidate();
        }

        return $result;
    }

    public function searchByRadius(float $latitude, float $longitude, float $radius, ?string $keyword = null, string|array|null $category = null, int $page = 1): LengthAwarePaginator
    {
        return $this->remember(
            $this->key('searchByRadius', [$latitude, $longitude, $radius, $keyword, $category, $page]),
            fn () => $this->eloquentRepo->searchByRadius($latitude, $longitude, $radius, $keyword, $category, $page),
        );
    }

    public function getNearbyMarkers(float $latitude, float $longitude, float $radius, ?string $keyword = null, string|array|null $category = null): Collection
    {
        return $this->remember(
            $this->key('getNearbyMarkers', [$latitude, $longitude, $radius, $keyword, $category]),
            fn () => $this->eloquentRepo->getNearbyMarkers($latitude, $longitude, $radius, $keyword, $category),
        );
    }
}
