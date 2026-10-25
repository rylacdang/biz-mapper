<?php

namespace App\Repositories\Contracts;

use App\Models\Business;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BusinessRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Increment the profile counter without changing listing edit timestamps.
     */
    public function incrementProfileViews(int $businessId): bool;

    /**
     * Load fresh profile views and published-review metrics for owned listings.
     */
    public function getOwnerInsights(int $ownerId, array $businessIds): Collection;

    /**
     * Find a business profile with its category or throw when missing.
     */
    public function findBySlugWithCategoryOrFail(string $slug): Business;

    /**
     * Count businesses matching base slug pattern for uniqueness check.
     */
    public function getSlugPatternCount(string $slug): int;

    /**
     * Get all businesses with category relation.
     */
    public function getAllWithCategory(): Collection;

    /**
     * Paginate the owner's listings with independent name and status filters.
     */
    public function paginateByUserIdWithCategory(int $userId, string $query = '', string $status = 'all', int $page = 1): LengthAwarePaginator;

    /**
     * Get businesses by category with category relation.
     */
    public function getByCategoryWithCategory(string $categoryName): Collection;

    /**
     * Get all soft-deleted businesses for user.
     */
    public function getTrashedByUserIdWithCategory(int|string $userId): Collection;

    /**
     * Find a soft-deleted business or throw when missing.
     */
    public function findTrashedOrFail(int|string $id): Model;

    /**
     * Restore soft-deleted business.
     */
    public function restoreBusiness(int|string $id): bool;

    /**
     * Search businesses inside a bounding box pre-filter sorted by Haversine distance.
     */
    public function searchByRadius(float $latitude, float $longitude, float $radius, ?string $keyword = null, string|array|null $category = null, int $page = 1): LengthAwarePaginator;

    /**
     * Get lightweight pins for the same nearby search.
     */
    public function getNearbyMarkers(float $latitude, float $longitude, float $radius, ?string $keyword = null, string|array|null $category = null): Collection;
}
