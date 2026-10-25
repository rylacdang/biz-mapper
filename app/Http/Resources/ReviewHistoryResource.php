<?php

namespace App\Http\Resources;

use App\Enums\ReviewStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewHistoryResource extends JsonResource
{
    /**
     * Show the author's feedback without exposing private business or account fields.
     */
    public function toArray(Request $request): array
    {
        $business = $this->business;
        $available = $business && ! $business->trashed();
        $anchor = $this->status === ReviewStatus::PUBLISHED ? 'review-'.$this->id : 'reviews-title';

        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'body' => $this->body,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'reply' => $this->reply ? [
                'body' => $this->reply->body,
                'created_at' => $this->reply->created_at->toISOString(),
                'updated_at' => $this->reply->updated_at->toISOString(),
            ] : null,
            'business' => $business ? ['name' => $business->name, 'logo' => $business->logo] : null,
            'url' => $available
                ? route('businesses.show', ['slug' => $business->slug, 'review' => $this->id], false).'#'.$anchor
                : null,
        ];
    }
}
