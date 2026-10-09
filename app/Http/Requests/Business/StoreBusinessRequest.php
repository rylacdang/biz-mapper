<?php

namespace App\Http\Requests\Business;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessRequest extends FormRequest
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
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'address' => ['required', 'string', 'max:500'],
            'region_code' => ['nullable', 'string', 'max:10'],
            'province_code' => ['nullable', 'string', 'max:10'],
            'city_code' => ['nullable', 'string', 'max:10'],
            'barangay_code' => ['nullable', 'string', 'max:10'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'cover_photo' => ['required', 'image', 'max:5120'], // Max 5MB
            'logo' => ['nullable', 'image', 'max:2048'], // Max 2MB
            'replace_gallery' => ['sometimes', 'boolean'],
            'gallery' => ['sometimes', 'nullable', 'array', 'max:5'],
            'gallery.*' => ['array:path,file'],
            'gallery.*.path' => ['nullable', 'required_without:gallery.*.file', 'prohibits:gallery.*.file', 'distinct:strict', 'string', Rule::in([])],
            'gallery.*.file' => ['nullable', 'required_without:gallery.*.path', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'hours' => ['required', 'array'],
            'hours.*.open' => ['nullable', 'date_format:H:i'],
            'hours.*.close' => ['nullable', 'date_format:H:i'],
            'hours.*.closed' => ['required', 'boolean'],
        ];
    }
}
