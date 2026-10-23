<?php

namespace App\Http\Requests\Consumer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionRequest extends FormRequest
{
    /**
     * Leave authorization to the controller policy checks.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the name for both creation and renaming.
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:80']];
    }
}
