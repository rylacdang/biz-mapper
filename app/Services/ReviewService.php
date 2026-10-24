<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Http\Resources\ReviewResource;
use App\Models\Business;
use App\Models\Review;
use App\Models\User;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviewRepo
    ) {}

    /**
     * Keep the public list separate from the viewer's own review.
     */
    public function pageData(Business $business, ?User $user, int $page = 1): array
    {
        $own = $user?->role === UserRole::CONSUMER
            ? $this->reviewRepo->forUser($business->id, $user->id) : null;

        return [
            'reviews' => ReviewResource::collection($this->reviewRepo->forBusiness($business->id, $page))->response()->getData(true),
            'summary' => $this->reviewRepo->summary($business->id),
            'own_review' => $own ? (new ReviewResource($own))->resolve() : null,
            'can_review' => $user?->role === UserRole::CONSUMER && $user->id !== $business->user_id,
            'can_reply' => $user?->role === UserRole::OWNER && $user->id === $business->user_id,
        ];
    }

    /**
     * Enforce one review even when two submissions arrive together.
     */
    public function create(Business $business, User $user, array $data): Review
    {
        try {
            return DB::transaction(function () use ($business, $user, $data) {
                $this->reviewRepo->lockBusiness($business->id);

                return $this->reviewRepo->create([
                    'business_id' => $business->id,
                    'user_id' => $user->id,
                    'rating' => $data['rating'],
                    'body' => $data['body'],
                    'status' => ReviewStatus::PUBLISHED,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['body' => 'You already reviewed this business. Edit your existing review instead.']);
        }
    }

    /**
     * Update the review and invalidate its summary atomically.
     */
    public function update(Review $review, array $data): void
    {
        DB::transaction(function () use ($review, $data) {
            $this->reviewRepo->lockBusiness($review->business_id);
            $this->reviewRepo->update($review->id, $data);
        });
    }

    /**
     * Delete the review and invalidate its summary atomically.
     */
    public function delete(Review $review): void
    {
        DB::transaction(function () use ($review) {
            $this->reviewRepo->lockBusiness($review->business_id);
            $this->reviewRepo->delete($review->id);
        });
    }
}
