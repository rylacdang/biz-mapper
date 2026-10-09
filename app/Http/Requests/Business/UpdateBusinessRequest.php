<?php

namespace App\Http\Requests\Business;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $business = $this->route('business');

        return $business && $this->user()->can('update', $business);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'min:10'],
            'address' => ['sometimes', 'string', 'max:500'],
            'region_code' => ['nullable', 'string', 'max:10'],
            'province_code' => ['nullable', 'string', 'max:10'],
            'city_code' => ['nullable', 'string', 'max:10'],
            'barangay_code' => ['nullable', 'string', 'max:10'],
            'lat' => ['sometimes', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'cover_photo' => ['nullable', 'image', 'max:5120'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'replace_gallery' => ['sometimes', 'boolean'],
            'gallery' => ['sometimes', 'nullable', 'array', 'max:5'],
            'gallery.*' => ['array:path,file'],
            'gallery.*.path' => ['nullable', 'required_without:gallery.*.file', 'prohibits:gallery.*.file', 'distinct:strict', 'string', Rule::in($this->route('business')->gallery_photos ?? [])],
            'gallery.*.file' => ['nullable', 'required_without:gallery.*.path', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'hours' => ['sometimes', 'array'],
            'hours.*.open' => ['nullable', 'date_format:H:i'],
            'hours.*.close' => ['nullable', 'date_format:H:i'],
            'hours.*.closed' => ['sometimes', 'boolean'],
        ];
    }
}
