<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouseId = (int) $this->route('id');

        return [
            'code' => ['required', 'string', 'max:64', 'unique:warehouses,code,'.$warehouseId],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

