<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessOfferingResource extends JsonResource
{
    /**
     * Expose only the details needed by the catalog.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'description' => $this->description,
            'photo' => $this->photo,
            'price' => $this->price,
            'price_max' => $this->price_max,
            'currency' => $this->currency,
            'price_unit' => $this->price_unit,
        ];
    }
}
