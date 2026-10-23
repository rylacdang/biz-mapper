<?php

namespace App\Http\Requests\Business;

use App\Enums\DiscoveryShortcut;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchNearbyBusinessesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'include_markers' => ['sometimes', 'boolean'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['required', 'numeric', 'min:0.1', 'max:50'],
            'keyword' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shortcut' => ['sometimes', 'nullable', 'string', Rule::enum(DiscoveryShortcut::class)],
            'category' => ['sometimes', 'nullable', 'string', 'max:255', Rule::when($this->filled('shortcut'), Rule::in(['all']))],
        ];
    }
}
