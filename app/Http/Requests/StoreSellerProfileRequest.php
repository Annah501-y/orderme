<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSellerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('seller') ?? false;
    }

    public function rules(): array
    {
        return [
            'store_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],

            'store_description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'phone' => [
                'nullable',
                'string',
                'regex:/^\+255[67]\d{8}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'store_name.required' => 'Please enter your store name.',
            'store_name.min' => 'The store name must be at least 2 characters.',
            'store_description.max' => 'The store description cannot exceed 1000 characters.',
            'phone.regex' => 'The phone number must be a valid Tanzanian number starting with +255 followed by 6 or 7.',
        ];
    }
}