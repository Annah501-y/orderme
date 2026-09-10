<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $this->user()?->can('products.update')
            && (
                $this->user()->hasRole('admin')
                || $product->seller_id === $this->user()->id
            );
    }

    public function rules(): array
    {
        return [
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'is_active.required' => 'Please specify whether the product should be active.',
            'is_active.boolean' => 'The product status must be true or false.',
        ];
    }
}