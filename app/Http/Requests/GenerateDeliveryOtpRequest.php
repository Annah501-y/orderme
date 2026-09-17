<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateDeliveryOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('rider') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}