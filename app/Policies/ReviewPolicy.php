<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy extends BasePolicy
{
    /**
     * Consumers may review an available business they do not own.
     */
    public function create(User $user, Business $business): bool
    {
        return $user->role === UserRole::CONSUMER
            && ! $business->trashed()
            && ! $this->ownsResource($user, $business);
    }

    /**
     * Only the consumer who wrote a review can change it.
     */
    public function update(User $user, Review $review): bool
    {
        return $user->role === UserRole::CONSUMER && $this->ownsResource($user, $review);
    }

    /**
     * Only the author can remove their review.
     */
    public function delete(User $user, Review $review): bool
    {
        return $this->update($user, $review);
    }
}
