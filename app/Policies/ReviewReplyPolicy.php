<?php

namespace App\Policies;

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Review;
use App\Models\User;

class ReviewReplyPolicy extends BasePolicy
{
    /**
     * Only this business's owner can reply to its published reviews.
     */
    public function manage(User $user, Review $review): bool
    {
        return $user->role === UserRole::OWNER
            && $review->status === ReviewStatus::PUBLISHED
            && $review->business !== null
            && $this->ownsResource($user, $review->business);
    }
}
