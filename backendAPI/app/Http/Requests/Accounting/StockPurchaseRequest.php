<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StockPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'accounting_period_id' => ['required', 'integer', 'exists:accounting_periods,id'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required', 'numeric', 'gt:0'],
            'credit_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'description' => ['nullable', 'string'],
            'reference_type' => ['nullable', 'string'],
            'reference_id' => ['nullable', 'integer'],
        ];
    }
}

