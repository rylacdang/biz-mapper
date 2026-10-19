<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

class IndexBusinessOfferingsRequest extends FormRequest
{
    /**
     * Authorization is handled by the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Bound catalog page requests.
     */
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1', 'max:100000']];
    }
}
