<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
                'distinct',
                'exists:cart_items,id',
            ],

            'address_id'=>[
                'required',
                'integer',
                'Exists:addresses,id',
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
                'Please select at least one item to checkout.',

            'cart_item_ids.array' =>
                'Invalid checkout items.',

            'cart_item_ids.min' =>
                'Please select at least one item to checkout.',

            'cart_item_ids.*.exists' =>
                'One of the selected cart items no longer exists.',
            'address_id' =>
            'please select a delivery address.',
            'vehicle_type.in' =>
            'please select either Bodaboda or Bajaji.',
        ];
    }
}