<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BusinessRepositoryInterface extends BaseRepositoryInterface
{
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
     * Find soft-deleted business by ID.
     */
    public function findTrashed(int|string $id): ?Model;

    /**
     * Restore soft-deleted business.
     */
    public function restoreBusiness(int|string $id): bool;
}
