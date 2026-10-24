<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewSummaryResource extends JsonResource
{
    /**
     * Expose only public summary fields.
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource['status'],
            'text' => $this->resource['text'],
            'generated_at' => $this->resource['generated_at'],
            'review_count' => $this->resource['review_count'],
            'sampled_review_count' => $this->resource['sampled_review_count'],
        ];
    }
}
