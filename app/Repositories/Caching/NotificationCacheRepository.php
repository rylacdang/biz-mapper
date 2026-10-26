<?php

namespace App\Repositories\Caching;

use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NotificationCacheRepository implements NotificationRepositoryInterface
{
    public function __construct(
        private readonly NotificationRepositoryInterface $eloquentRepo
    ) {}

    public function forUser(User $user, int $page = 1): LengthAwarePaginator
    {
        return $this->remember($user, 'page:'.$page, fn () => $this->eloquentRepo->forUser($user, $page));
    }

    public function unreadCount(User $user): int
    {
        return $this->remember($user, 'unread', fn () => $this->eloquentRepo->unreadCount($user));
    }

    public function markRead(User $user, string $notification): ?string
    {
        $readAt = $this->eloquentRepo->markRead($user, $notification);

        if ($readAt !== null) {
            Cache::tags(['notifications:'.$user->role->value.':'.$user->id])->flush();
        }

        return $readAt;
    }

    public function markAllRead(User $user): string
    {
        $readAt = $this->eloquentRepo->markAllRead($user);
        Cache::tags(['notifications:'.$user->role->value.':'.$user->id])->flush();

        return $readAt;
    }

    public function invalidate(): void
    {
        if (DB::connection()->transactionLevel() > 0) {
            DB::afterCommit(fn () => Cache::tags(['notifications'])->flush());

            return;
        }

        Cache::tags(['notifications'])->flush();
    }

    /**
     * Keep results isolated and expire cached bell queries after 15 seconds.
     */
    private function remember(User $user, string $key, callable $callback): mixed
    {
        if (DB::connection()->transactionLevel() > 0) {
            return $callback();
        }

        $cache = Cache::tags(['notifications', 'notifications:'.$user->role->value.':'.$user->id]);
        $key = $cache->getTags()->getNamespace().':'.$key;
        $result = unserialize($cache->remember($key, 15, fn () => serialize($callback())));

        if ($result instanceof LengthAwarePaginator) {
            $result->withPath(Paginator::resolveCurrentPath());
        }

        return $result;
    }
}
