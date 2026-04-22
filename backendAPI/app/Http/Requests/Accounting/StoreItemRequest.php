<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'unique:items,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:inventory,service,non-inventory'],
            'unit' => ['required', 'string', 'max:32'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'cost_method' => ['required', 'in:average,fifo,lifo'],
            'inventory_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'cogs_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'revenue_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'inventory_adjustment_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'goods_in_transit_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

