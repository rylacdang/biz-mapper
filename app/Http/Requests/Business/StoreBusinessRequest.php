<?php

namespace App\Http\Requests\Business;

use App\Enums\BusinessOfferingType;
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
            'offerings' => ['sometimes', 'nullable', 'array', 'max:20'],
            'offerings.*' => ['array:type,name,description,photo,price,price_max,currency,price_unit'],
            'offerings.*.type' => ['required', Rule::enum(BusinessOfferingType::class)],
            'offerings.*.name' => ['required', 'string', 'max:160'],
            'offerings.*.description' => ['nullable', 'string', 'max:2000'],
            'offerings.*.photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'offerings.*.price' => ['nullable', 'required_with:offerings.*.price_max', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'offerings.*.price_max' => ['nullable', 'numeric', 'decimal:0,2', 'gte:offerings.*.price', 'max:9999999999.99'],
            'offerings.*.currency' => ['required_with:offerings.*.price', 'nullable', Rule::in(config('business.currencies'))],
            'offerings.*.price_unit' => ['nullable', 'string', 'max:50'],
            'hours' => ['required', 'array'],
            'hours.*.open' => ['nullable', 'date_format:H:i'],
            'hours.*.close' => ['nullable', 'date_format:H:i'],
            'hours.*.closed' => ['required', 'boolean'],
        ];
    }
}
