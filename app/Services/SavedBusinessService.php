<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Repositories\Contracts\SavedBusinessRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SavedBusinessService
{
    public function __construct(
        private readonly SavedBusinessRepositoryInterface $savedRepo
    ) {}

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
