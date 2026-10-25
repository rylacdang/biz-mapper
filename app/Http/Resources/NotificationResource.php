<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Keep the existing bell response without exposing review or account details.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'title' => $this->resource['title'],
            'message' => $this->resource['message'],
            'url' => $this->resource['url'],
            'created_at' => $this->resource['created_at'],
            'read_at' => $this->resource['read_at'],
        ];
    }
}
