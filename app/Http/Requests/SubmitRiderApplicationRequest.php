<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitRiderApplicationRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:rider_applications,email',
                'unique:users,email',
            ],
            'phone' => [
                'required',
                'string',
                'regex:/^[67]\d{8}$/',
                'unique:rider_applications,phone',
                'unique:users,phone',
            ],
        ];
    }
}
