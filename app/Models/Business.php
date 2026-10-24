<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Observers\BusinessSummaryObserver;
use App\Policies\BusinessPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
    'social_links',
    'cover_photo',
    'logo',
    'gallery_photos',
    'hours',
    'status',
    'view_count',
    'region_code',
    'province_code',
    'city_code',
    'barangay_code',
])]
#[UsePolicy(BusinessPolicy::class)]
#[ObservedBy([BusinessSummaryObserver::class])]
class Business extends Model
{
    use SoftDeletes;

    /**
     * Get the current generated review summary.
     */
    public function reviewSummary(): HasOne
    {
        return $this->hasOne(ReviewSummary::class);
    }

    /**
     * Get this business's consumer reviews.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get the business's products and services.
     */
    public function offerings(): HasMany
    {
        return $this->hasMany(BusinessOffering::class);
    }

    /**
     * Cast attributes
     */
    public function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'status' => BusinessStatus::class,
            'view_count' => 'integer',
            'gallery_photos' => 'array',
            'social_links' => 'array',
        ];
    }

    /**
     * Read and save hours with boolean closed flags.
     */
    protected function hours(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?array => $value === null
                ? null
                : $this->normalizeHours(json_decode($value, true, 512, JSON_THROW_ON_ERROR)),
            set: fn (?array $value): ?string => $value === null
                ? null
                : json_encode($this->normalizeHours($value), JSON_THROW_ON_ERROR),
        );
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
        return $query->where('status', BusinessStatus::ACTIVE);
    }

    /**
     * Convert each day's closed flag to a boolean.
     */
    private function normalizeHours(array $hours): array
    {
        foreach ($hours as &$day) {
            if (is_array($day) && array_key_exists('closed', $day)) {
                // Multipart uploads send boolean values as strings.
                $day['closed'] = filter_var($day['closed'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $hours;
    }
}
