<?php

namespace App\Http\Requests\Consumer;

use Illuminate\Foundation\Http\FormRequest;

class CollectionPageRequest extends FormRequest
{
    /**
     * Leave authorization to the controller policy checks.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Bound collection and saved-place pagination.
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
