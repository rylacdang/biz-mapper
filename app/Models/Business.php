<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'category_id',
    'name',
    'slug',
    'description',
    'address',
    'lat',
    'lng',
    'phone',
    'website',
    'cover_photo',
    'logo',
    'hours',
    'status',
    'view_count',
    'region_code',
    'province_code',
    'city_code',
    'barangay_code'
])]
class Business extends Model
{
    use SoftDeletes;

    /**
     * Cast attributes
     */
    public function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'hours' => 'array',
            'status' => BusinessStatus::class,
            'view_count' => 'integer',
        ];
    }

    /**
     * Get business owner
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get business category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope active businesses
     */
    public function scopeActive($query)
    {
        return $query->where('status', \App\Enums\BusinessStatus::ACTIVE);
    }
}
