<?php

namespace App\Repositories\Eloquents;

use App\Models\BusinessOffering;
use App\Repositories\Contracts\BusinessOfferingRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BusinessOfferingRepository extends BaseRepository implements BusinessOfferingRepositoryInterface
{
    public function __construct(BusinessOffering $offering)
    {
        parent::__construct($offering);
    }

    public function forBusiness(int $businessId, int $page = 1, int $perPage = 12, bool $newestFirst = false): LengthAwarePaginator
    {
        $query = $this->query()->where('business_id', $businessId);

        if ($newestFirst) {
            $query->orderByDesc('id');
        } else {
            $query->orderBy('type')->orderBy('id');
        }

        $results = $query->paginate($perPage, ['*'], 'page', $page);

        if ($page > $results->lastPage()) {
            return $query->paginate($perPage, ['*'], 'page', $results->lastPage(), $results->total());
        }

        return $results;
    }
}
