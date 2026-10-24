<?php

namespace App\Repositories\Eloquents;

use App\Enums\ReviewStatus;
use App\Enums\ReviewSummaryStatus;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewSummary;
use App\Repositories\Contracts\ReviewSummaryRepositoryInterface;

class ReviewSummaryRepository extends BaseRepository implements ReviewSummaryRepositoryInterface
{
    public function __construct(ReviewSummary $summary)
    {
        parent::__construct($summary);
    }

    public function forBusiness(int $businessId): ?ReviewSummary
    {
        return $this->query()->where('business_id', $businessId)->first();
    }

    public function lockBusiness(int $businessId): ?Business
    {
        return Business::withTrashed()->select(['id', 'deleted_at'])->lockForUpdate()->find($businessId);
    }

    public function snapshot(int $businessId, ?int $excludingUser = null): array
    {
        $query = Review::query()->where('business_id', $businessId)
            ->where('status', ReviewStatus::PUBLISHED)
            ->when($excludingUser !== null, fn ($query) => $query->where('user_id', '!=', $excludingUser));

        $count = (clone $query)->count();

        $reviews = $query->orderByDesc('id')->limit(50)
            ->get(['id', 'rating', 'body', 'updated_at'])->map(fn (Review $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'body' => $review->body,
                'updated_at' => $review->updated_at->toISOString(),
            ])->all();

        return [
            'count' => $count,
            'reviews' => $reviews,
            'fingerprint' => hash('sha256', json_encode([$count, $reviews], JSON_THROW_ON_ERROR)),
        ];
    }

    public function businessIdsForUser(int $userId): array
    {
        return Review::where('user_id', $userId)->distinct()->orderBy('business_id')->pluck('business_id')->all();
    }

    public function missingBusinessIds(): iterable
    {
        return Business::query()->whereDoesntHave('reviewSummary')
            ->whereHas('reviews', fn ($query) => $query->where('status', ReviewStatus::PUBLISHED), '>=', 3)
            ->select('id')->lazyById(100)->pluck('id');
    }

    public function recoverableSummaries(bool $retryUnavailable = false): iterable
    {
        return $this->query()->whereHas('business')->where(function ($query) use ($retryUnavailable) {
            $query->where(fn ($query) => $query->where('status', ReviewSummaryStatus::PENDING)
                ->where('next_generation_at', '<=', now()))
                ->orWhere(fn ($query) => $query->where('status', ReviewSummaryStatus::GENERATING)
                    ->where('generation_started_at', '<=', now()->subSeconds(120)));

            if ($retryUnavailable) {
                $query->orWhere('status', ReviewSummaryStatus::UNAVAILABLE);
            }
        })->lazyById(100);
    }
}
