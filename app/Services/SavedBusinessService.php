<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Repositories\Contracts\SavedBusinessRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SavedBusinessService
{
    public function __construct(private readonly SavedBusinessRepositoryInterface $savedRepo) {}

    /**
     * Save the business for the authenticated consumer.
     */
    public function save(User $user, Business $business): void
    {
        $this->savedRepo->saveForUser($user->id, $business->id);
    }

    /**
     * Remove only this consumer's saved business.
     */
    public function remove(User $user, Business $business): void
    {
        $this->savedRepo->removeForUser($user->id, $business->id);
    }

    /**
     * Add private bookmark state after public records leave the cache.
     */
    public function markSaved(Business|Collection $businesses, ?User $user): void
    {
        $this->savedRepo->markSaved(
            $businesses instanceof Business ? new Collection([$businesses]) : $businesses,
            $user?->role === UserRole::CONSUMER ? $user->id : null,
        );
    }
}
