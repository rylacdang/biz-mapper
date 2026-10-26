<?php

namespace App\Repositories\Caching;

use App\Repositories\Contracts\CategoryRepositoryInterface;

class CategoryCacheRepository extends CacheBaseRepository implements CategoryRepositoryInterface
{
    protected int $ttl = 3600;

    protected array $relatedTags = ['businesses', 'saved_businesses', 'place_collections'];

    public function __construct(CategoryRepositoryInterface $eloquentRepo)
    {
        parent::__construct($eloquentRepo, 'categories');
    }
}
