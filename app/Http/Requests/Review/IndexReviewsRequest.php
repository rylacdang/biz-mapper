<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class IndexReviewsRequest extends FormRequest
{
    /**
     * Keep authorization in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate review input.
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000']
        ];
    }
}
