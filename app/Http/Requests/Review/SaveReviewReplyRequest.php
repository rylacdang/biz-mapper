<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class SaveReviewReplyRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:2', 'max:2000']
        ];
    }
}
