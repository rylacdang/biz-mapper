<?php

namespace App\Repositories\Caching;

use App\Models\PlaceCollection;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlaceCollectionCacheRepository extends CacheBaseRepository implements PlaceCollectionRepositoryInterface
{
    public function __construct(PlaceCollectionRepositoryInterface $eloquentRepo)
    {
        parent::__construct($eloquentRepo, 'place_collections');
    }

    public function forUser(int $userId, int $page = 1): LengthAwarePaginator
    {
        return $this->remember(
            $this->key('forUser', [$userId, $page]),
            fn () => $this->eloquentRepo->forUser($userId, $page),
        );
    }

    public function findPublicByTokenOrFail(string $token): PlaceCollection
    {
        return $this->eloquentRepo->findPublicByTokenOrFail($token);
    }

    public function lockForChanges(int $id): PlaceCollection
    {
        return $this->eloquentRepo->lockForChanges($id);
    }

    public function publicPlaces(PlaceCollection $collection, int $page = 1): LengthAwarePaginator
    {
        return $this->remember(
            $this->key('publicPlaces', [$collection->id, $collection->user_id, $page]),
            fn () => $this->eloquentRepo->publicPlaces($collection, $page),
        );
    }

    public function places(PlaceCollection $collection, int $page = 1): LengthAwarePaginator
    {
        return $this->remember(
            $this->key('places', [$collection->id, $collection->user_id, $page]),
            fn () => $this->eloquentRepo->places($collection, $page),
        );
    }

    public function availablePlaces(PlaceCollection $collection, int $page = 1): LengthAwarePaginator
    {
        return $this->remember(
            $this->key('availablePlaces', [$collection->id, $collection->user_id, $page]),
            fn () => $this->eloquentRepo->availablePlaces($collection, $page),
        );
    }

    public function addPlace(int $collectionId, int $savedBusinessId): void
    {
        $this->eloquentRepo->addPlace($collectionId, $savedBusinessId);

        $this->invalidate();
    }

    public function movePlace(PlaceCollection $collection, int $savedBusinessId, string $direction): void
    {
        $this->eloquentRepo->movePlace($collection, $savedBusinessId, $direction);

        $this->invalidate();
    }

    public function removePlace(int $collectionId, int $savedBusinessId): void
    {
        $this->eloquentRepo->removePlace($collectionId, $savedBusinessId);

        $this->invalidate();
    }
}
