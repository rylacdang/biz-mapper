<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessMarkerResource extends JsonResource
{
    /**
     * Keep map pins independent from full business records.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'category_id' => $this->category_id,
        ];
    }
}
