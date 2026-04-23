<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (int) ($this->route('id') ?? 0);

        return [
            'payment_no' => [
                'required',
                'string',
                'max:50',
                Rule::unique('payments', 'payment_no')->ignore($id),
            ],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
        ];
    }
}

