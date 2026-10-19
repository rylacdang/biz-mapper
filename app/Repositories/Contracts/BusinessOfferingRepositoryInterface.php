<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BusinessOfferingRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Load a bounded page from one business's catalog.
     */
    public function forBusiness(int $businessId, int $page = 1, int $perPage = 12, bool $newestFirst = false): LengthAwarePaginator;
}
