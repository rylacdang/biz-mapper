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
            'destination' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
