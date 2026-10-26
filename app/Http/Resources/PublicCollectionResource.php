<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCollectionResource extends JsonResource
{
    /**
     * Share the collection without exposing its owner or private record IDs.
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'share_token' => $this->share_token,
            'places_count' => (int) $this->places_count,
            'url' => route('collections.show', $this->share_token),
            'map_url' => route('explorer.index', ['collection' => $this->share_token]),
        ];
    }
}
