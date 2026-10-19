<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\BusinessOffering;
use App\Models\User;

class BusinessOfferingPolicy extends BasePolicy
{
    /**
     * Allow owners to add items only to their own live business.
     */
    public function create(User $user, Business $business): bool
    {
        return ! $business->trashed() && $this->ownsResource($user, $business);
    }

    /**
     * Allow changes only to the owner's own catalog items.
     */
    public function update(User $user, BusinessOffering $offering): bool
    {
        return $offering->business !== null && $this->ownsResource($user, $offering->business);
    }

    /**
     * Use the same ownership rule for removing an item.
     */
    public function delete(User $user, BusinessOffering $offering): bool
    {
        return $this->update($user, $offering);
    }
}
