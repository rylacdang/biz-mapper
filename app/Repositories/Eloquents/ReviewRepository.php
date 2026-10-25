<?php

namespace App\Repositories\Eloquents;

use App\Enums\ReviewStatus;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReviewRepository extends BaseRepository implements ReviewRepositoryInterface
{
    public function __construct(Review $review)
    {
        parent::__construct($review);
    }

    public function lockBusiness(int $businessId): void
    {
        Business::withTrashed()->lockForUpdate()->findOrFail($businessId);
    }

    public function forBusiness(int $businessId, int $page = 1): LengthAwarePaginator
    {
        $query = $this->query()->where('business_id', $businessId)
            ->where('status', ReviewStatus::PUBLISHED)->with(['author:id,name', 'reply'])
            ->orderByDesc('id');
        $results = $query->paginate(10, ['*'], 'page', $page);

        return $page > $results->lastPage()
            ? $query->paginate(10, ['*'], 'page', $results->lastPage(), $results->total())
            : $results;
    }

    public function summary(int $businessId): array
    {
        $summary = $this->query()->where('business_id', $businessId)
            ->where('status', ReviewStatus::PUBLISHED)
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')->first();

        return [
            'count' => (int) $summary->total,
            'average' => $summary->average === null ? null : round((float) $summary->average, 1),
        ];
    }

    public function forUser(int $businessId, int $userId): ?Review
    {
        return $this->query()->where('business_id', $businessId)->where('user_id', $userId)
            ->with(['author:id,name', 'reply'])->first();
    }

    public function saveReply(int $reviewId, int $userId, string $body): void
    {
        ReviewReply::updateOrCreate(['review_id' => $reviewId], ['user_id' => $userId, 'body' => $body]);
    }

    public function deleteReply(int $reviewId): void
    {
        ReviewReply::where('review_id', $reviewId)->delete();
    }

    public function pageForReview(int $businessId, int $reviewId): int
    {
        $published = $this->query()->where('business_id', $businessId)->where('status', ReviewStatus::PUBLISHED);

        if (! (clone $published)->whereKey($reviewId)->exists()) {
            return 1;
        }

        return intdiv($published->where('id', '>', $reviewId)->count(), 10) + 1;
    }
}
