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

    public function places(PlaceCollection $collection, int $page = 1): LengthAwarePaginator
    {
        $query = $this->savedPlacesQuery($collection)
            ->join('collection_places', 'collection_places.saved_business_id', '=', 'saved_businesses.id')
            ->where('collection_places.place_collection_id', $collection->id)
            ->selectRaw('1 as in_collection')
            ->orderByDesc('collection_places.id');

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

    private function savedPlacesQuery(PlaceCollection $collection): Builder
    {
        return Business::query()
            ->join('saved_businesses', 'saved_businesses.business_id', '=', 'businesses.id')
            ->where('saved_businesses.user_id', $collection->user_id)
            ->select('businesses.*', 'saved_businesses.id as saved_id')
            ->selectRaw('1 as is_saved')
            ->with('category');
    }

    private function paginateQuery(Builder $query, int $page): LengthAwarePaginator
    {
        $results = $query->paginate(20, ['*'], 'page', $page);

        if ($page > $results->lastPage()) {
            return $query->paginate(20, ['*'], 'page', $results->lastPage(), $results->total());
        }

        return $results;
    }
}
