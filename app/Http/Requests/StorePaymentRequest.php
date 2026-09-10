<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => [
                'required',
                'string',
                Rule::in([
                    'mpesa',
                    'airtel_money',
                    'yas',
                    'halopesa',
                    'card',
                    'crdb',
                    'nmb',
                ]),
            ],
            'phone_number' => [
                'required_if:method,mpesa,airtel_money,yas,halopesa',
                'nullable',
                'string',
                'max:20',
            ],
        ];
    }
    public function messages(): array  
    {
        return [
            'method.required'=> 'please select a payment method',
        ];
    }
}
