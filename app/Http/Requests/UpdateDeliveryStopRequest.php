<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryStopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('rider') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                'in:arrived,completed',
            ],
        ];
    }
}