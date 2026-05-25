<?php

namespace App\Repositories\Eloquents;

use App\Models\Business;
use App\Repositories\Contracts\BusinessRepositoryInterface;

class BusinessRepository extends BaseRepository implements BusinessRepositoryInterface
{
    public function __construct(Business $business)
    {
        parent::__construct($business);
    }

    public function getSlugPatternCount(string $slug): int
    {
        return $this->query()
            ->whereRaw("slug RLIKE '^{$slug}(-[0-9]+)?$'")
            ->count();
    }
}
