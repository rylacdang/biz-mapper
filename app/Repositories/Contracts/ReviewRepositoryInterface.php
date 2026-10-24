<?php

namespace App\Repositories\Contracts;

use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReviewRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Paginate published reviews with authors and replies.
     */
    public function forBusiness(int $businessId, int $page = 1): LengthAwarePaginator;

    /**
     * Calculate ratings using published reviews only.
     */
    public function summary(int $businessId): array;

    /**
     * Find the viewer's review, including hidden reviews.
     */
    public function forUser(int $businessId, int $userId): ?Review;

    /**
     * Create or replace the single owner reply.
     */
    public function saveReply(int $reviewId, int $userId, string $body): void;

    /**
     * Remove a review's reply.
     */
    public function deleteReply(int $reviewId): void;
}
