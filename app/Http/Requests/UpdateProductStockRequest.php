<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $this->user()?->can('products.manage_stock')
            && (
                $this->user()->hasRole('admin')
                || $product->seller_id === $this->user()->id
            );
    }

    public function rules(): array
    {
        return [
            'stock_quantity' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }
}