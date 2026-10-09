<?php

namespace App\Repositories\Eloquents;

use App\Models\Business;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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

    public function findBySlugWithCategoryOrFail(string $slug): Business
    {
        return $this->query()
            ->with('category')
            ->where('slug', $slug)
            ->firstOrFail();
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

    public function getTrashedByUserIdWithCategory(int|string $userId): Collection
    {
        return $this->query()
            ->onlyTrashed()
            ->where('user_id', $userId)
            ->with('category')
            ->get();
    }

    public function findTrashedOrFail(int|string $id): Model
    {
        return $this->query()
            ->onlyTrashed()
            ->findOrFail($id);
    }

    public function restoreBusiness(int|string $id): bool
    {
        $record = $this->findTrashedOrFail($id);

        return $record->restore();
    }

    public function searchByRadius(float $latitude, float $longitude, float $radius, ?string $keyword = null, ?string $category = null): Collection
    {
        $earthRadius = 6371;

        $query = $this->query()->with('category');

        // Calculate Bounding Box coordinates (Pre-filter to prevent full table scan)
        $latDelta = rad2deg($radius / $earthRadius);
        $lngDelta = rad2deg($radius / $earthRadius / cos(deg2rad($latitude)));

        $minLat = $latitude - $latDelta;
        $maxLat = $latitude + $latDelta;
        $minLng = $longitude - $lngDelta;
        $maxLng = $longitude + $lngDelta;

        $query->whereBetween('lat', [$minLat, $maxLat])
            ->whereBetween('lng', [$minLng, $maxLng]);

        // If keyword is provided, filter by keyword (name or description)
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        // Category filter
        if ($category && $category !== 'all') {
            $query->whereHas('category', function ($q) use ($category) {
                $q->where('name', $category)
                    ->orWhere('id', $category);
            });
        }

        // Calculate exact Haversine Distance in Kilometers
        $distanceFormula = "{$earthRadius} * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))";

        $query->select('*')
            ->selectRaw("({$distanceFormula}) AS distance", [$latitude, $longitude, $latitude]);

        // Always enforce the proximity radius constraint
        $query->having('distance', '<=', $radius);

        return $query->orderBy('distance', 'asc')->get();
    }
}
