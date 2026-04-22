<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\Concerns\ResolvesReportPeriod;
use Illuminate\Validation\ValidationException;

class TrialBalanceService
{
    use ResolvesReportPeriod;

    /**
     * Trial Balance (Neraca Saldo) - posted journals only.
     *
     * @return array{
     *   accounts: list<array{
     *     account_id: int,
     *     account_code: string,
     *     account_name: string,
     *     total_debit: float,
     *     total_credit: float,
     *     balance: float
     *   }>,
     *   total_debit: float,
     *   total_credit: float
     * }
     */
    public function getTrialBalance(?int $periodId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        [$dateFrom, $dateTo] = $this->resolvePeriod($periodId, $dateFrom, $dateTo);

        $rows = Account::query()
            ->select([
                'accounts.id',
                'accounts.code',
                'accounts.name',
            ])
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->join('journal_lines', 'journal_lines.account_id', '=', 'accounts.id')
            ->join('journal_entries', function ($join) use ($dateFrom, $dateTo): void {
                $join->on('journal_entries.id', '=', 'journal_lines.journal_entry_id')
                    ->where('journal_entries.status', '=', 'posted');

                if ($dateFrom !== null) {
                    $join->where('journal_entries.date', '>=', $dateFrom);
                }

                if ($dateTo !== null) {
                    $join->where('journal_entries.date', '<=', $dateTo);
                }
            })
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code')
            ->get();

        $accounts = $rows->map(function (Account $account): array {
            $debit = round((float) $account->total_debit, 2);
            $credit = round((float) $account->total_credit, 2);

            return [
                'account_id' => (int) $account->id,
                'account_code' => (string) $account->code,
                'account_name' => (string) $account->name,
                'total_debit' => $debit,
                'total_credit' => $credit,
                'balance' => round($debit - $credit, 2),
            ];
        })->values();

        $totalDebit = round($accounts->sum('total_debit'), 2);
        $totalCredit = round($accounts->sum('total_credit'), 2);

        if ($totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'trial_balance' => ['Trial balance is not balanced (total debit must equal total credit).'],
            ]);
        }

        return [
            'accounts' => $accounts->all(),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
        ];
    }
}

