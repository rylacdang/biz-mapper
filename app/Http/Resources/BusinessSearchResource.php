<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessSearchResource extends JsonResource
{
    /**
     * Preserve the public nearby-search response.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'address' => $this->address,
            'hours' => $this->hours,
            'phone' => $this->phone,
            'website' => $this->website,
            'social_links' => $this->social_links ?? [],
            'cover_photo' => $this->cover_photo,
            'logo' => $this->logo,
            'gallery_photos' => $this->gallery_photos ?? [],
            'lat' => $this->lat,
            'lng' => $this->lng,
            'distance' => (float) $this->distance,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'color' => $this->category->color,
                'icon' => $this->category->icon,
            ] : null,
        ];
    }
}
