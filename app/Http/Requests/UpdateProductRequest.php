<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'category_id' => [
                'sometimes',
                'integer',
                'exists:categories,id',
            ],

            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],

            'old_price' => [
                'sometimes',
                'required_with:discount',
                'numeric',
                'min:0.01',
            ],

            'discount' => [
                'sometimes',
                'required_with:old_price',
                'numeric',
                'min:0',
                'max:100',
            ],

            'stock_quantity' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'image' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }
}