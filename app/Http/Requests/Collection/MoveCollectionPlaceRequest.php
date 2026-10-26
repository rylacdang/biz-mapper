<?php

namespace App\Http\Requests\Collection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveCollectionPlaceRequest extends FormRequest
{
    /**
     * Let the controller authorize the collection and save together.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept one adjacent move instead of a client-supplied membership list.
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['up', 'down'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
