<?php

namespace App\Http\Requests\Explorer;

use Illuminate\Foundation\Http\FormRequest;

class IndexExplorerRequest extends FormRequest
{
    /**
     * Allow public access to the explorer.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the initial category filter.
     */
    public function rules(): array
    {
        return ['category' => ['sometimes', 'string']];
    }
}
