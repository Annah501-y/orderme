<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSellerProfileRequest extends FormRequest
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
            'store_name' => [
                'required',
                'string',
                'min:2',
                'max:150',
            ],
            'store_description' => [
                'nullable',
                'string',
                'max:100',

            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',

            ],
            'address' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }
}
