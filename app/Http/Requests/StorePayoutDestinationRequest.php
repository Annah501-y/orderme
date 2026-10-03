<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayoutDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['seller', 'rider']) ?? false;
    }

    public function rules(): array
    {
        return [
            'payout_method' => [
                'required',
                'string',
                'in:mobile_money,bank',
            ],

            'mobile_phone' => [
                'exclude_unless:payout_method,mobile_money',
                'required',
                'string',
                'regex:/^255[67]\d{8}$/',
            ],

            'bank_bic' => [
                'exclude_unless:payout_method,bank',
                'required',
                'string',
                'max:20',
            ],

            'bank_account_number' => [
                'exclude_unless:payout_method,bank',
                'required',
                'string',
                'max:50',
            ],

            'bank_account_name' => [
                'exclude_unless:payout_method,bank',
                'required',
                'string',
                'max:255',
            ],
        ];
    }
}
