<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy extends BasePolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Business $business): bool
    {
        return $this->ownsResource($user, $business);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Business $business): bool
    {
        return $this->ownsResource($user, $business);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Business $business): bool
    {
        return $this->ownsResource($user, $business);
    }
}
