<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class BasePolicy
{
    /**
     * Check ownership of a user-owned resource.
     */
    protected function ownsResource(User $user, Model $resource): bool
    {
        return $user->getKey() !== null
            && $resource->getAttribute('user_id') === $user->getKey();
    }
}
