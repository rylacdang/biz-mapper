<?php

namespace App\Repositories\Eloquents;

use App\Models\Business;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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

    public function searchByRadius(float $latitude, float $longitude, float $radius, ?string $keyword = null, ?string $category = null, int $page = 1): LengthAwarePaginator
    {
        $query = $this->nearbyQuery($latitude, $longitude, $radius, $keyword, $category)
            ->addSelect('businesses.*')
            ->with('category')
            ->orderBy('distance')
            ->orderBy('id');
        $results = $query->paginate(20, ['*'], 'page', $page);

        // A saved page can disappear after listings are deleted.
        if ($page > $results->lastPage()) {
            return $query->paginate(20, ['*'], 'page', $results->lastPage(), $results->total());
        }

        return $results;
    }

    public function getNearbyMarkers(float $latitude, float $longitude, float $radius, ?string $keyword = null, ?string $category = null): Collection
    {
        return $this->nearbyQuery($latitude, $longitude, $radius, $keyword, $category)
            ->addSelect(['id', 'slug', 'lat', 'lng', 'category_id'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Apply the same radius, keyword, and category filters to results and map pins.
     */
    private function nearbyQuery(float $latitude, float $longitude, float $radius, ?string $keyword, ?string $category): Builder
    {
        $earthRadius = 6371;
        $latDelta = rad2deg($radius / $earthRadius);
        $lngDelta = min(180, rad2deg($radius / $earthRadius / max(abs(cos(deg2rad($latitude))), 0.000001)));

        $query = $this->query()
            ->whereBetween('lat', [$latitude - $latDelta, $latitude + $latDelta]);

        // Longitude wraps at the date line; keep the bounding box valid there.
        if ($lngDelta < 180) {
            $minLng = $longitude - $lngDelta;
            $maxLng = $longitude + $lngDelta;

            if ($minLng < -180) {
                $query->where(fn (Builder $q) => $q->where('lng', '>=', $minLng + 360)->orWhere('lng', '<=', $maxLng));
            } elseif ($maxLng > 180) {
                $query->where(fn (Builder $q) => $q->where('lng', '>=', $minLng)->orWhere('lng', '<=', $maxLng - 360));
            } else {
                $query->whereBetween('lng', [$minLng, $maxLng]);
            }
        }

        if ($keyword) {
            $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$keyword}%")->orWhere('description', 'like', "%{$keyword}%"));
        }

        if ($category && $category !== 'all') {
            $query->whereHas('category', fn (Builder $q) => $q->where('name', $category)->orWhere('id', $category));
        }

        // Clamp rounding errors so a business at the exact center is not lost.
        $cosine = 'cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat))';
        $distance = "{$earthRadius} * acos(CASE WHEN ({$cosine}) > 1 THEN 1 WHEN ({$cosine}) < -1 THEN -1 ELSE ({$cosine}) END)";
        $bindings = array_merge(...array_fill(0, 3, [$latitude, $longitude, $latitude]));

        return $query->selectRaw("({$distance}) AS distance", $bindings)
            ->whereRaw("({$distance}) <= ?", [...$bindings, $radius]);
    }
}
