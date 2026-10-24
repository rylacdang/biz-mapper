<?php

namespace App\Models;

use App\Policies\ReviewReplyPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['review_id', 'user_id', 'body'])]
#[UsePolicy(ReviewReplyPolicy::class)]
class ReviewReply extends Model
{
    /**
     * Get the review this reply belongs to.
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
