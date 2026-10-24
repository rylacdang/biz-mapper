<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Expose review content without account contact details.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'body' => $this->body,
            'status' => $this->status->value,
            'author' => $this->author->name,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
            'reply' => $this->reply ? [
                'body' => $this->reply->body,
                'created_at' => $this->reply->created_at->toISOString(),
                'updated_at' => $this->reply->updated_at->toISOString(),
            ] : null,
        ];
    }
}
