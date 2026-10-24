<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Observers\ReviewObserver;
use App\Policies\ReviewPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['business_id', 'user_id', 'rating', 'body', 'status'])]
#[UsePolicy(ReviewPolicy::class)]
#[ObservedBy([ReviewObserver::class])]
class Review extends Model
{
    /**
     * Cast review values consistently.
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
        ];
    }

    /**
     * Get the reviewed business.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the consumer who wrote this review.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the owner's reply.
     */
    public function reply(): HasOne
    {
        return $this->hasOne(ReviewReply::class);
    }
}
