<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SavedBusinessRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Paginate only the consumer's available saved places.
     */
    public function forUser(int $userId, int $page = 1): LengthAwarePaginator;

    /**
     * Save a place without creating duplicate records.
     */
    public function saveForUser(int $userId, int $businessId): void;

    /**
     * Remove only the requesting consumer's saved record.
     */
    public function removeForUser(int $userId, int $businessId): void;

    /**
     * Load bookmark states for the visible businesses in one query.
     */
    public function markSaved(Collection $businesses, ?int $userId): void;
}
