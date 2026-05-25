<?php

namespace App\Repositories\Contracts;

interface BusinessRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Count businesses matching base slug pattern for uniqueness check.
     */
    public function getSlugPatternCount(string $slug): int;
}
