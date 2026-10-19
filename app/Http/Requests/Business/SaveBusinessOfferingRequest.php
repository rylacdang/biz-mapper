<?php

namespace App\Http\Requests\Business;

use App\Enums\BusinessOfferingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBusinessOfferingRequest extends FormRequest
{
    /**
     * Authorization is handled by the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate optional photos and explicit price information.
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(BusinessOfferingType::class)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'photo' => [Rule::prohibitedIf($this->boolean('remove_photo')), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photo' => ['sometimes', 'boolean'],
            'price' => ['nullable', 'required_with:price_max', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'price_max' => ['nullable', 'numeric', 'decimal:0,2', 'gte:price', 'max:9999999999.99'],
            'currency' => [Rule::requiredIf($this->input('price') !== null), 'nullable', Rule::in(config('business.currencies'))],
            'price_unit' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Use clear names in validation messages.
     */
    public function attributes(): array
    {
        return [
            'price_max' => 'maximum price',
            'price_unit' => 'price unit',
        ];
    }
}
