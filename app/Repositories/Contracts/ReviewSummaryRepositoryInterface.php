<?php

namespace App\Repositories\Contracts;

use App\Models\Business;
use App\Models\ReviewSummary;

interface ReviewSummaryRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find summary state without using the listing cache.
     */
    public function forBusiness(int $businessId): ?ReviewSummary;

    /**
     * Serialize summary changes with review writes.
     */
    public function lockBusiness(int $businessId): ?Business;

    /**
     * Capture published source data with a bounded review sample.
     */
    public function snapshot(int $businessId, ?int $excludingUser = null): array;

    /**
     * Find businesses affected by an account deletion.
     */
    public function businessIdsForUser(int $userId): array;

    /**
     * Find existing businesses that have no summary state yet.
     */
    public function missingBusinessIds(): iterable;

    /**
     * Find due work and abandoned generations.
     */
    public function recoverableSummaries(bool $retryUnavailable = false): iterable;
}
