<?php

namespace App\Repositories\Contracts;

use App\Models\Business;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BusinessRepositoryInterface extends BaseRepositoryInterface
{
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
     * Get businesses by user ID with category relation.
     */
    public function getByUserIdWithCategory(int|string $userId): Collection;

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
    public function searchByRadius(float $latitude, float $longitude, float $radius, ?string $keyword = null, ?string $category = null): Collection;
}
