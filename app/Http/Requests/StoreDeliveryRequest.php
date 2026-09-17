<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'order_id' => [
                'required',
                'integer',
                'exists:orders,id',
            ],

            'rider_id' => [
                'required',
                'integer',
                'exists:riders,id',
            ],

            'seller_order_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'seller_order_ids.*' => [
                'integer',
                'distinct',
                'exists:seller_orders,id',
            ],

            'stops' => [
                'required',
                'array',
                'min:2',
            ],

            'stops.*.seller_order_id' => [
                'nullable',
                'integer',
                'exists:seller_orders,id',
            ],

            'stops.*.stop_type' => [
                'required',
                'string',
                'in:pickup,delivery',
            ],

            'stops.*.sequence' => [
                'required',
                'integer',
                'min:1',
            ],

            'stops.*.address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'stops.*.latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'stops.*.longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
        ];
    }
}