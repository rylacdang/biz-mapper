<?php

namespace App\Models;

use App\Enums\ReviewSummaryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id',
    'status',
    'text',
    'revision',
    'generation_attempts',
    'source_fingerprint',
    'review_count',
    'sampled_review_count',
    'generated_at',
    'next_generation_at',
    'generation_started_at',
])]
class ReviewSummary extends Model
{
    /**
     * Keep persisted summary state typed.
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewSummaryStatus::class,
            'revision' => 'integer',
            'generation_attempts' => 'integer',
            'review_count' => 'integer',
            'sampled_review_count' => 'integer',
            'generated_at' => 'immutable_datetime',
            'next_generation_at' => 'immutable_datetime',
            'generation_started_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the business this summary describes.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
