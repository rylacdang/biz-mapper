<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaceCollectionResource extends JsonResource
{
    /**
     * Return only the collection fields used in the consumer workspace.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'places_count' => (int) $this->places_count,
        ];
    }
}
