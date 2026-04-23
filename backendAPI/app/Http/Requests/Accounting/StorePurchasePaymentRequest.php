<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchasePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_no' => ['required', 'string', 'max:50', 'unique:purchase_payments,payment_no'],
            'purchase_invoice_id' => ['required', 'integer', 'exists:purchase_invoices,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            'credit_account_id' => ['required', 'integer', 'exists:accounts,id'],
        ];
    }
}

