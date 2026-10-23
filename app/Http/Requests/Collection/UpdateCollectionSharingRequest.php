<?php

namespace App\Http\Requests\Collection;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionSharingRequest extends FormRequest
{
    /**
     * Keep ownership checks in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Require an explicit sharing choice.
     */
    public function rules(): array
    {
        return ['is_public' => ['required', 'boolean']];
    }
}
