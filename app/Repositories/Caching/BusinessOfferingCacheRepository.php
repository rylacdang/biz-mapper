<?php

namespace App\Repositories\Caching;

use App\Repositories\Contracts\BusinessOfferingRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BusinessOfferingCacheRepository extends CacheBaseRepository implements BusinessOfferingRepositoryInterface
{
    public function __construct(BusinessOfferingRepositoryInterface $eloquentRepo)
    {
        parent::__construct($eloquentRepo, 'business_offerings');
    }

    public function forBusiness(int $businessId, int $page = 1, int $perPage = 12, bool $newestFirst = false): LengthAwarePaginator
    {
        return $this->remember(
            $this->key('forBusiness', [$businessId, $page, $perPage, $newestFirst]),
            fn () => $this->eloquentRepo->forBusiness($businessId, $page, $perPage, $newestFirst),
        );
    }
}
