<?php

namespace App\Services;

use App\Models\PlaceCollection;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CollectionOrderingService
{
    public function __construct(
        private readonly PlaceCollectionRepositoryInterface $collectionRepo,
    ) {}

    /**
     * Append new saves while serializing concurrent changes to this collection.
     */
    public function add(PlaceCollection $collection, int $savedBusinessId): void
    {
        DB::transaction(function () use ($collection, $savedBusinessId) {
            $this->collectionRepo->lockForChanges($collection->id);
            $this->collectionRepo->addPlace($collection->id, $savedBusinessId);
        });
    }

    /**
     * Keep removals from racing an adjacent move or addition.
     */
    public function remove(PlaceCollection $collection, int $savedBusinessId): void
    {
        DB::transaction(function () use ($collection, $savedBusinessId) {
            $this->collectionRepo->lockForChanges($collection->id);
            $this->collectionRepo->removePlace($collection->id, $savedBusinessId);
        });
    }

    /**
     * Move across page boundaries by swapping two available neighbors atomically.
     */
    public function move(PlaceCollection $collection, int $savedBusinessId, string $direction): void
    {
        DB::transaction(function () use ($collection, $savedBusinessId, $direction) {
            $locked = $this->collectionRepo->lockForChanges($collection->id);
            $this->collectionRepo->movePlace($locked, $savedBusinessId, $direction);
        });
    }
}
