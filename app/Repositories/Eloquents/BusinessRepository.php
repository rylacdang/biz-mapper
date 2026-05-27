<?php

namespace App\Repositories\Eloquents;

use App\Models\Business;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

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

    public function getAllWithCategory(): Collection
    {
        return $this->query()->with('category')->get();
    }

    public function getByUserIdWithCategory(int|string $userId): Collection
    {
        return $this->query()
            ->where('user_id', $userId)
            ->with('category')
            ->get();
    }

    public function getByCategoryWithCategory(string $categoryName): Collection
    {
        $query = $this->query()->with('category');

        if ($categoryName !== 'all') {
            $query->whereHas('category', function ($q) use ($categoryName) {
                $q->where('name', $categoryName);
            });
        }

        return $query->get();
    }
}
