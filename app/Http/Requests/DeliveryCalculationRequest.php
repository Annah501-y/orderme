<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cart_item_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'cart_item_ids.*' => [
                'integer',
                'exists:cart_items,id',
            ],

            'address_id' => [
                'required',
                'integer',
                'exists:addresses,id',
            ],

            'vehicle_type' => [
                'required',
                'in:bodaboda,bajaji',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'cart_item_ids.required' =>
                'Please select at least one cart item.',

            'address_id.required' =>
                'Please select a delivery address.',

            'vehicle_type.in' =>
                'Please select either Bodaboda or Bajaji.',
        ];
    }
}