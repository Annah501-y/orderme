<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'phone' => [
                'required',
                'string',
                'regex:/^\+255[67]\d{8}$/',
                'unique:users,phone',
            ],

            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],

            'account_type' => [
                'required',
                Rule::in([
                    'buyer',
                    'seller',
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your full name.',

            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already registered.',

            'phone.required' => 'Please enter your Tanzanian phone number.',
            'phone.regex' => 'The phone number must be a valid Tanzanian number starting with +255 followed by 6 or 7.', 
            'phone.unique' => 'This phone number is already registered.',

            'password.required' => 'Please create a password.',
            'password.confirmed' => 'The password confirmation does not match.',

            'account_type.required' => 'Please select whether you are registering as a buyer or seller.',
            'account_type.in' => 'Account type must be either buyer or seller.',
        ];
    }

    public function attributes(): array
    {
        return [
            'account_type' => 'account type',
        ];
    }
}