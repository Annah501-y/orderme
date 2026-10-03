<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSellerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['buyer', 'seller']) ?? false;
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

            'nida_number' => [
                'required',
                'string',
                'max:50',
            ],

            'tin_reference' => [
                'required',
                'string',
                'max:50',
            ],

            'business_license' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],

            'payout_method' => [
                'required',
                'string',
                'in:mobile_money,bank',
            ],

            'mobile_phone' => [
                'exclude_unless:payout_method,mobile_money',
                'required',
                'string',
                'regex:/^255[67]\d{8}$/',
            ],

            'bank_bic' => [
                'exclude_unless:payout_method,bank',
                'required',
                'string',
                'max:20',
            ],

            'bank_account_number' => [
                'exclude_unless:payout_method,bank',
                'required',
                'string',
                'max:50',
            ],

            'bank_account_name' => [
                'exclude_unless:payout_method,bank',
                'required',
                'string',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'regex:/^[67]\d{8}$/',
            ],

            'address_line' => [
                'required',
                'string',
                'max:255',
            ],

            'district' => [
                'required',
                'string',
                'max:100',
            ],

            'city' => [
                'required',
                'string',
                'max:100',
            ],

            'region' => [
                'required',
                'string',
                'max:100',
            ],

            'country' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'store_name.required' => 'Please enter your store name.',
            'store_name.min' => 'The store name must be at least 2 characters.',
            'store_description.max' => 'The store description cannot exceed 1000 characters.',
            'phone.regex' => 'Enter 9 digits starting with 6 or 7.',

            'address_line.required' => 'Please enter your store address.',
            'district.required' => 'Please enter your district.',
            'city.required' => 'Please enter your city.',
            'region.required' => 'Please enter your region.',
        ];
    }
}
