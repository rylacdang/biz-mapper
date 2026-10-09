<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

class GetDirectionsRequest extends FormRequest
{
    /**
     * Allow guests to request walking directions.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the starting point and destination identifier.
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Keep the destination tied to the requested route.
     */
    public function validationData(): array
    {
        return [...$this->all(), 'slug' => $this->route('slug')];
    }
}
