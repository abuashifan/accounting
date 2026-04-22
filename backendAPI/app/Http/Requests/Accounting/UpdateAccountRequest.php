<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountId = (int) $this->route('id');

        return [
            'code' => ['required', 'string', 'max:64', 'unique:accounts,code,'.$accountId],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id', 'not_in:'.$accountId],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

