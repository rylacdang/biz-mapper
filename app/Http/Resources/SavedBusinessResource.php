<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SavedBusinessResource extends BusinessProfileResource
{
    /**
     * Include private membership controls only in authorized collection responses.
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'saved_id' => (int) $this->saved_id,
            'in_collection' => (bool) $this->in_collection,
        ];
    }
}
