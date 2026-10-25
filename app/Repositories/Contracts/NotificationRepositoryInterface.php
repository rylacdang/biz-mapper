<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    /**
     * Read this person's existing reviews or owner replies.
     */
    public function forUser(User $user, int $page = 1): LengthAwarePaginator;

    /**
     * Count activity not covered by this person's cached read markers.
     */
    public function unreadCount(User $user): int;

    /**
     * Mark owned activity read and return its read time, or null if unavailable.
     */
    public function markRead(User $user, string $notification): ?string;

    /**
     * Mark current activity read without hiding later reviews or replies.
     */
    public function markAllRead(User $user): string;
}
