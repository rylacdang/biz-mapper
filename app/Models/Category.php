<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'icon', 'color'])]
class Category extends Model
{
    /**
     * Get related businesses
     */
    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }
}
