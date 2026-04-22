<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class TrialBalanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_id' => ['nullable', 'integer', 'exists:accounting_periods,id'],
            'date_from' => ['nullable', 'date', 'required_without:period_id'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from', 'required_without:period_id'],
        ];
    }
}
