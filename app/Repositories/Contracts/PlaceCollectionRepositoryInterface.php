<?php

namespace App\Repositories\Contracts;

use App\Models\PlaceCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PlaceCollectionRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * List only this consumer's collections with available-place counts.
     */
    public function forUser(int $userId, int $page = 1): LengthAwarePaginator;

    /**
     * Find a currently published collection by its public link token.
     */
    public function findPublicByTokenOrFail(string $token): PlaceCollection;

    /**
     * Lock the collection while changing its stable sharing link.
     */
    public function lockForSharing(int $id): PlaceCollection;

    /**
     * Paginate public business fields without private saved-record attributes.
     */
    public function publicPlaces(PlaceCollection $collection, int $page = 1): LengthAwarePaginator;

    /**
     * Paginate the available saved places in a collection.
     */
    public function places(PlaceCollection $collection, int $page = 1): LengthAwarePaginator;

    /**
     * Paginate the owner's saves with their current membership state.
     */
    public function availablePlaces(PlaceCollection $collection, int $page = 1): LengthAwarePaginator;

    /**
     * Add an authorized save without duplicate membership.
     */
    public function addPlace(int $collectionId, int $savedBusinessId): void;

    /**
     * Remove membership while keeping the original save.
     */
    public function removePlace(int $collectionId, int $savedBusinessId): void;
}
