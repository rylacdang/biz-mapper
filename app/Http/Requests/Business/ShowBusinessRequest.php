<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

class ShowBusinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the profile identifier.
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Read the slug from the route rather than a query parameter.
     */
    public function validationData(): array
    {
        return ['slug' => $this->route('slug')];
    }
}
