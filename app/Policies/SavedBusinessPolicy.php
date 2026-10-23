<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;

class SavedBusinessPolicy extends BasePolicy
{
    /**
     * Keep saved places exclusive to consumers.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::CONSUMER;
    }

    /**
     * Allow consumers to save an available business.
     */
    public function create(User $user, Business $business): bool
    {
        return $this->viewAny($user) && ! $business->trashed();
    }

    /**
     * Allow removal even when a saved business is no longer available.
     */
    public function delete(User $user, Business $business): bool
    {
        return $this->viewAny($user);
    }
}
