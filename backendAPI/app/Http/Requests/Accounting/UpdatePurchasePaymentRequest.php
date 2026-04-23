<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchasePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paymentId = (int) $this->route('id');

        return [
            'payment_no' => [
                'required',
                'string',
                'max:50',
                Rule::unique('purchase_payments', 'payment_no')->ignore($paymentId),
            ],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'credit_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'description' => ['nullable', 'string'],
        ];
    }
}

