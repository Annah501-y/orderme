<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest {
    /**
    * Determine if the user is authorized to make this request.
    */

    public function authorize(): bool {
        return $this->user() !== null;
    }

    /**
    * Get the validation rules that apply to the request.
    *
    * @return array<string, ValidationRule|array<mixed>|string>
    */

    public function rules(): array {
        return [
            'name'=>[
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:100'
            ],
            'email'=>[
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique( 'users', 'email' )->ignore( $this->user()->id ),
            ],
            'phone'=>[
                'sometimes',
                'required',
                'string',
                Rule::unique( 'users', 'phone' )->ignore( $this->user()->id ),
            ],
            'profile_photo' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }

    public function messages(): array {
        return [
            'name.required' => 'Please enter your full name.',
            'name.min' => 'Your name must be at least 2 characters.',

            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already registered.',

            'phone.required' => 'Please enter your Tanzanian phone number.',
            'phone.regex' => 'The phone number must be a valid Tanzanian phone number starting with +255 followed by 6 or 7.',
            'phone.unique' => 'This phone number is already registered.',
        ];
    }
}

