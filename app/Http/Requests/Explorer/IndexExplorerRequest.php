<?php

namespace App\Http\Requests\Explorer;

use App\Enums\DiscoveryShortcut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        return [
            'shortcut' => ['sometimes', 'nullable', 'string', Rule::enum(DiscoveryShortcut::class)],
            'category' => ['sometimes', 'string', Rule::when($this->filled('shortcut'), Rule::in(['all']))],
            'collection' => ['sometimes', 'nullable', 'uuid'],
            'collection_page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'destination' => ['sometimes', 'nullable', 'string', 'max:255'],
            'listings_query' => ['sometimes', 'nullable', 'string', 'max:100'],
            'listings_status' => ['sometimes', 'string', Rule::in(['all', 'active', 'pending'])],
            'listings_page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
