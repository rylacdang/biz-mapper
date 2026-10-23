<?php

namespace App\Repositories\Eloquents;

use App\Models\Business;
use App\Models\PlaceCollection;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PlaceCollectionRepository extends BaseRepository implements PlaceCollectionRepositoryInterface
{
    public function __construct(PlaceCollection $collection)
    {
        parent::__construct($collection);
    }

    public function forUser(int $userId, int $page = 1): LengthAwarePaginator
    {
        return $this->paginateQuery(
            $this->query()
                ->where('user_id', $userId)
                ->withCount([
                    'savedPlaces as places_count' => fn (Builder $query) => $query
                        ->where('user_id', $userId)
                        ->whereHas('business'),
                ])
                ->orderByDesc('id'),
            $page,
        );
    }

    public function findPublicByTokenOrFail(string $token): PlaceCollection
    {
        return $this->query()->where('share_token', $token)->where('is_public', true)->firstOrFail();
    }

    public function lockForSharing(int $id): PlaceCollection
    {
        return $this->query()->lockForUpdate()->findOrFail($id);
    }

    public function publicPlaces(PlaceCollection $collection, int $page = 1): LengthAwarePaginator
    {
        return $this->paginateQuery($this->collectionPlacesQuery($collection), $page);
    }

    public function places(PlaceCollection $collection, int $page = 1): LengthAwarePaginator
    {
        $query = $this->collectionPlacesQuery($collection)
            ->addSelect('saved_businesses.id as saved_id')
            ->selectRaw('1 as is_saved, 1 as in_collection');

        return $this->paginateQuery($query, $page);
    }

    public function availablePlaces(PlaceCollection $collection, int $page = 1): LengthAwarePaginator
    {
        $query = $this->savedPlacesQuery($collection)
            ->selectSub(
                DB::table('collection_places')->selectRaw('COUNT(*)')
                    ->whereColumn('saved_business_id', 'saved_businesses.id')
                    ->where('place_collection_id', $collection->id),
                'in_collection',
            )
            ->orderByDesc('saved_businesses.id');

        return $this->paginateQuery($query, $page);
    }

    public function addPlace(int $collectionId, int $savedBusinessId): void
    {
        DB::table('collection_places')->insertOrIgnore([
            'place_collection_id' => $collectionId,
            'saved_business_id' => $savedBusinessId,
        ]);
    }

    public function removePlace(int $collectionId, int $savedBusinessId): void
    {
        DB::table('collection_places')
            ->where('place_collection_id', $collectionId)
            ->where('saved_business_id', $savedBusinessId)
            ->delete();
    }

    /**
     * Share the same owner and availability constraints between private and public members.
     */
    private function collectionPlacesQuery(PlaceCollection $collection): Builder
    {
        return $this->availableBusinessesQuery($collection)
            ->join('collection_places', 'collection_places.saved_business_id', '=', 'saved_businesses.id')
            ->where('collection_places.place_collection_id', $collection->id)
            ->orderByDesc('collection_places.id');
    }

    /**
     * Limit business data to the collection owner's available saves.
     */
    private function availableBusinessesQuery(PlaceCollection $collection): Builder
    {
        return Business::query()
            ->join('saved_businesses', 'saved_businesses.business_id', '=', 'businesses.id')
            ->where('saved_businesses.user_id', $collection->user_id)
            ->select('businesses.*')
            ->with('category');
    }

    /**
     * Build a query for available businesses saved by the collection owner.
     */
    private function savedPlacesQuery(PlaceCollection $collection): Builder
    {
        return $this->availableBusinessesQuery($collection)
            ->addSelect('saved_businesses.id as saved_id')
            ->selectRaw('1 as is_saved');
    }

    /**
     * Paginate 20 records and return the last page if the requested page no longer exists.
     */
    private function paginateQuery(Builder $query, int $page): LengthAwarePaginator
    {
        $results = $query->paginate(20, ['*'], 'page', $page);

        if ($page > $results->lastPage()) {
            return $query->paginate(20, ['*'], 'page', $results->lastPage(), $results->total());
        }

        return $results;
    }
}
