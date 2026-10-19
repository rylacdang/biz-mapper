<?php

namespace App\Models;

use App\Enums\BusinessOfferingType;
use App\Policies\BusinessOfferingPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'type', 'name', 'description', 'photo', 'price', 'price_max', 'currency', 'price_unit'])]
#[UsePolicy(BusinessOfferingPolicy::class)]
class BusinessOffering extends Model
{
    /**
     * Preserve money as decimal strings rather than floating point values.
     */
    protected function casts(): array
    {
        return [
            'type' => BusinessOfferingType::class,
            'price' => 'decimal:2',
            'price_max' => 'decimal:2',
        ];
    }

    /**
     * Get the business that owns this catalog item.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
