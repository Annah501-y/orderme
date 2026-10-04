<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->user() !== null) {
            return true;
        }

        // A guest may pay only for the order matching its stored token hash.
        $order = $this->route('order');
        $guestToken = (string) $this->input('guest_access_token');

        return $order instanceof Order
            && $order->user_id === null
            && $guestToken !== ''
            && hash_equals(
                (string) $order->guest_access_token_hash,
                hash('sha256', $guestToken)
            );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => [
                'required',
                'string',
                Rule::in([
                    'mpesa',
                    'airtel_money',
                    'yas',
                    'halopesa',
                    'card',
                    'crdb',
                    'nmb',
                ]),
            ],
            'phone_number' => [
                'required_if:method,mpesa,airtel_money,yas,halopesa',
                'nullable',
                'string',
                'max:20',
            ],
            'guest_access_token' => $this->user()
                ? ['nullable', 'string']
                : ['required', 'string', 'size:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => 'please select a payment method',
        ];
    }
}
