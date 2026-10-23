<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PlaceCollection;
use App\Models\SavedBusiness;
use App\Models\User;

class PlaceCollectionPolicy extends BasePolicy
{
    /**
     * Allow consumers to browse their own collections.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::CONSUMER;
    }

    /**
     * Allow consumers to create private collections.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Keep collection details private to their owner.
     */
    public function view(User $user, PlaceCollection $collection): bool
    {
        return $this->viewAny($user) && $this->ownsResource($user, $collection);
    }

    /**
     * Allow guests to view a collection only while it is published.
     */
    public function viewPublic(?User $user, PlaceCollection $collection): bool
    {
        return $collection->is_public;
    }

    /**
     * Allow only the owner to rename a collection.
     */
    public function update(User $user, PlaceCollection $collection): bool
    {
        return $this->view($user, $collection);
    }

    /**
     * Allow only the owner to delete a collection.
     */
    public function delete(User $user, PlaceCollection $collection): bool
    {
        return $this->view($user, $collection);
    }

    /**
     * Both the collection and saved place must belong to this consumer.
     */
    public function managePlace(User $user, PlaceCollection $collection, SavedBusiness $savedBusiness): bool
    {
        return $this->update($user, $collection) && $this->ownsResource($user, $savedBusiness);
    }
}
