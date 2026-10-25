<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class IndexNotificationsRequest extends FormRequest
{
    /**
     * Authorization is handled in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Bound page input before querying the inbox.
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000']
        ];
    }
}
