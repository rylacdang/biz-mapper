<?php

namespace App\Models;

use App\Policies\SavedBusinessPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'business_id'])]
#[UsePolicy(SavedBusinessPolicy::class)]
class SavedBusiness extends Model
{
    /**
     * Get the consumer who saved this place.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the saved business while it is available.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
