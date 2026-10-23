<?php

namespace App\Repositories\Eloquents;

use App\Models\Business;
use App\Models\SavedBusiness;
use App\Repositories\Contracts\SavedBusinessRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SavedBusinessRepository extends BaseRepository implements SavedBusinessRepositoryInterface
{
    public function __construct(SavedBusiness $savedBusiness)
    {
        parent::__construct($savedBusiness);
    }

    public function forUser(int $userId, int $page = 1): LengthAwarePaginator
    {
        $query = Business::query()
            ->join('saved_businesses', 'saved_businesses.business_id', '=', 'businesses.id')
            ->where('saved_businesses.user_id', $userId)
            ->select('businesses.*')
            ->with('category')
            ->orderByDesc('saved_businesses.id');
        $results = $query->paginate(20, ['businesses.*'], 'page', $page);

        if ($page > $results->lastPage()) {
            $results = $query->paginate(20, ['businesses.*'], 'page', $results->lastPage(), $results->total());
        }

        $results->getCollection()->each(fn (Business $business) => $business->setAttribute('is_saved', true));

        return $results;
    }

    public function saveForUser(int $userId, int $businessId): void
    {
        $this->query()->firstOrCreate(['user_id' => $userId, 'business_id' => $businessId]);
    }

    public function removeForUser(int $userId, int $businessId): void
    {
        $this->query()
            ->where('user_id', $userId)
            ->where('business_id', $businessId)
            ->delete();
    }

    public function markSaved(Collection $businesses, ?int $userId): void
    {
        $ids = $userId && $businesses->isNotEmpty()
            ? $this->query()
                ->where('user_id', $userId)
                ->whereIn('business_id', $businesses->modelKeys())
                ->pluck('business_id')
                ->all()
            : [];

        $saved = array_flip($ids);

        $businesses->each(fn (Business $business) => $business->setAttribute('is_saved', isset($saved[$business->id])));
    }
}
