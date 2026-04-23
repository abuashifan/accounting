<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_no' => ['required', 'string', 'max:50', 'unique:payments,payment_no'],
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
        ];
    }
}

