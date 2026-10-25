<?php

namespace App\Repositories\Eloquents;

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function forUser(User $user, int $page = 1): LengthAwarePaginator
    {
        $state = $this->readState($user);
        $owner = $user->role === UserRole::OWNER;
        $query = $this->activities($user);
        $results = $query->orderByDesc('id')->paginate(10, ['*'], 'page', $page);

        $results->through(function ($activity) use ($state, $owner) {
            $review = $owner ? $activity : $activity->review;
            $business = $review->business;

            return [
                'id' => ($owner ? 'review-' : 'reply-').$activity->id,
                'title' => $owner ? 'New review' : 'Owner replied',
                'message' => $owner
                    ? "Someone reviewed {$business->name}."
                    : "The owner of {$business->name} replied to your review.",
                'url' => route('businesses.show', ['slug' => $business->slug, 'review' => $review->id], false).'#review-'.$review->id,
                'created_at' => $activity->created_at->toIso8601String(),
                'read_at' => $activity->id <= $state['through']
                    ? $state['read_at'] : ($state['ids'][$activity->id] ?? null),
            ];
        });

        return $results;
    }

    public function unreadCount(User $user): int
    {
        $state = $this->readState($user);

        return $this->activities($user)->where('id', '>', $state['through'])
            ->whereNotIn('id', array_keys($state['ids']))->count();
    }

    public function markRead(User $user, string $notification): ?string
    {
        $prefix = $user->role === UserRole::OWNER ? 'review-' : 'reply-';

        if (! preg_match('/^'.$prefix.'([1-9][0-9]*)$/', $notification, $matches)) {
            return null;
        }

        $id = $matches[1];

        if (! $this->activities($user)->whereKey($id)->exists()) {
            return null;
        }

        return Cache::lock($this->readKey($user).':lock', 5)->block(2, function () use ($user, $id) {
            $state = $this->readState($user);

            if ($id <= $state['through']) {
                return $state['read_at'];
            }

            $state['ids'][$id] ??= now()->toIso8601String();
            Cache::forever($this->readKey($user), $state);

            return $state['ids'][$id];
        });
    }

    public function markAllRead(User $user): string
    {
        return Cache::lock($this->readKey($user).':lock', 5)->block(2, function () use ($user) {
            $state = [
                'through' => (int) ($this->activities($user)->max('id') ?? 0),
                'read_at' => now()->toIso8601String(),
                'ids' => [],
            ];
            Cache::forever($this->readKey($user), $state);

            return $state['read_at'];
        });
    }

    /**
     * Scope every read and count to activity this account is allowed to see.
     */
    private function activities(User $user): Builder
    {
        if ($user->role === UserRole::OWNER) {
            return Review::query()->select(['id', 'business_id', 'created_at'])
                ->where('status', ReviewStatus::PUBLISHED)->where('user_id', '!=', $user->id)
                ->whereHas('business', fn (Builder $business) => $business->where('user_id', $user->id))
                ->with('business:id,name,slug');
        }

        return ReviewReply::query()->select(['id', 'review_id', 'created_at'])
            ->where('user_id', '!=', $user->id)
            ->whereHas('review', fn (Builder $review) => $review
                ->where('user_id', $user->id)->where('status', ReviewStatus::PUBLISHED)
                ->whereHas('business', fn (Builder $business) => $business
                    ->whereColumn('businesses.user_id', 'review_replies.user_id')))
            ->with(['review:id,business_id', 'review.business:id,name,slug']);
    }

    /**
     * Reuse the existing cache so the bell does not need its own table.
     */
    private function readState(User $user): array
    {
        return Cache::get($this->readKey($user), ['through' => 0, 'read_at' => null, 'ids' => []]);
    }

    /**
     * Keep read markers separate for each account and role.
     */
    private function readKey(User $user): string
    {
        return 'bell:read:'.$user->role->value.':'.$user->id;
    }
}
