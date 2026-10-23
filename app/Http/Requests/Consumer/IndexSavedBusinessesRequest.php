<?php

namespace App\Http\Requests\Consumer;

use Illuminate\Foundation\Http\FormRequest;

class IndexSavedBusinessesRequest extends FormRequest
{
    /**
     * Authorization is handled by the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Bound the saved-places page request.
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
