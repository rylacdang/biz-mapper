<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class SaveReviewRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:10', 'max:2000']
        ];
    }
}
