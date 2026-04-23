<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class ProductHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}

