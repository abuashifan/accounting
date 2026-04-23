<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_no' => ['required', 'string', 'max:50', 'unique:purchase_returns,return_no'],
            'return_date' => ['required', 'date'],
            'purchase_invoice_id' => ['required', 'integer', 'exists:purchase_invoices,id'],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'gt:0'],
        ];
    }
}

