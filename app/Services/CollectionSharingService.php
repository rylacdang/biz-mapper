<?php

namespace App\Services;

use App\Models\PlaceCollection;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CollectionSharingService
{
    public function __construct(
        private readonly PlaceCollectionRepositoryInterface $collectionRepo
    ) {}

    /**
     * Create one stable link on publication and disable access when made private.
     */
    public function update(PlaceCollection $collection, bool $public): void
    {
        DB::transaction(function () use ($collection, $public) {
            $locked = $this->collectionRepo->lockForSharing($collection->id);
            $attributes = ['is_public' => $public];

            if ($public && ! $locked->share_token) {
                $attributes['share_token'] = (string) Str::uuid();
            }

            $this->collectionRepo->update($locked->id, $attributes);
        });
    }
}
