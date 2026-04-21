<?php

namespace App\Domains\Accounting\Services;

use App\Models\Account;
use Illuminate\Validation\ValidationException;

class TrialBalanceService
{
    /**
     * @return array<string, mixed>
     */
    public function getTrialBalance(string $startDate, string $endDate): array
    {
        [$startDate, $endDate] = $this->normalizePeriod($startDate, $endDate);

        $accounts = Account::query()
            ->select([
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
            ])
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->join('journal_lines', 'journal_lines.account_id', '=', 'accounts.id')
            ->join('journal_entries', function ($join) use ($startDate, $endDate): void {
                $join->on('journal_entries.id', '=', 'journal_lines.journal_entry_id')
                    ->where('journal_entries.status', '=', 'posted')
                    ->whereBetween('journal_entries.date', [$startDate, $endDate]);
            })
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get()
            ->map(function (Account $account): array {
                $debit = round((float) $account->total_debit, 2);
                $credit = round((float) $account->total_credit, 2);

                return [
                    'account_id' => $account->id,
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'account_type' => $account->type,
                    'total_debit' => $debit,
                    'total_credit' => $credit,
                    'balance' => round($this->toBalanceDelta($account, $debit, $credit), 2),
                ];
            })
            ->values();

        $totalDebit = round($accounts->sum('total_debit'), 2);
        $totalCredit = round($accounts->sum('total_credit'), 2);

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'accounts' => $accounts->all(),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => round($totalDebit, 2) === round($totalCredit, 2),
            'difference' => round($totalDebit - $totalCredit, 2),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizePeriod(string $startDate, string $endDate): array
    {
        if ($startDate > $endDate) {
            throw ValidationException::withMessages([
                'end_date' => ['The end date must be greater than or equal to the start date.'],
            ]);
        }

        return [$startDate, $endDate];
    }

    private function toBalanceDelta(Account $account, float $debit, float $credit): float
    {
        if (in_array($account->type, ['asset', 'expense'], true)) {
            return $debit - $credit;
        }

        return $credit - $debit;
    }
}
