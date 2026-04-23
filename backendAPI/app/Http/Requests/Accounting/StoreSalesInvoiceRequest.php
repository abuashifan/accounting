<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_no' => ['required', 'string', 'max:50', 'unique:invoices,invoice_no'],
            'invoice_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}

