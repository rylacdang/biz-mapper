<?php

namespace App\Models;

use App\Policies\PlaceCollectionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'name', 'is_public', 'share_token', 'description'])]
#[UsePolicy(PlaceCollectionPolicy::class)]
class PlaceCollection extends Model
{
    /**
     * Keep the sharing flag a boolean in every response.
     */
    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    /**
     * Get the consumer who owns this collection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the saves organized into this collection.
     */
    public function savedPlaces(): BelongsToMany
    {
        return $this->belongsToMany(SavedBusiness::class, 'collection_places');
    }
}
