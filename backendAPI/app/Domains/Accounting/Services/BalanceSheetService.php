<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalLine;
use App\Domains\Accounting\Services\Concerns\ResolvesReportPeriod;
use Illuminate\Validation\ValidationException;

class BalanceSheetService
{
    use ResolvesReportPeriod;

    /**
     * Balance Sheet (Neraca) - posted journals only.
     *
     * Assets balance = SUM(debit) - SUM(credit)
     * Liabilities/Equity balance = SUM(credit) - SUM(debit)
     *
     * @return array{
     *   assets: list<array{account_id:int,account_code:string,account_name:string,balance:float}>,
     *   liabilities: list<array{account_id:int,account_code:string,account_name:string,balance:float}>,
     *   equity: list<array{account_id:int,account_code:string,account_name:string,balance:float}>
     * }
     */
    public function getBalanceSheet(?int $periodId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        [$dateFrom, $dateTo] = $this->resolvePeriod($periodId, $dateFrom, $dateTo);

        $rows = Account::query()
            ->select([
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
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
            ->whereIn('accounts.type', ['asset', 'liability', 'equity'])
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get();

        $assets = [];
        $liabilities = [];
        $equity = [];

        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;

        foreach ($rows as $row) {
            $debit = round((float) $row->total_debit, 2);
            $credit = round((float) $row->total_credit, 2);
            $balance = match ($row->type) {
                'asset' => round($debit - $credit, 2),
                default => round($credit - $debit, 2),
            };

            $payload = [
                'account_id' => (int) $row->id,
                'account_code' => (string) $row->code,
                'account_name' => (string) $row->name,
                'balance' => $balance,
            ];

            if ($row->type === 'asset') {
                $assets[] = $payload;
                $totalAssets = round($totalAssets + $balance, 2);
            } elseif ($row->type === 'liability') {
                $liabilities[] = $payload;
                $totalLiabilities = round($totalLiabilities + $balance, 2);
            } else {
                $equity[] = $payload;
                $totalEquity = round($totalEquity + $balance, 2);
            }
        }

        $netProfit = $this->calculateNetProfit($dateFrom, $dateTo);

        if ($netProfit !== 0.0) {
            $equity[] = [
                'account_id' => 0,
                'account_code' => 'CURRENT_PROFIT_LOSS',
                'account_name' => 'Current Period Profit/Loss',
                'balance' => $netProfit,
            ];
            $totalEquity = round($totalEquity + $netProfit, 2);
        }

        if (round($totalAssets, 2) !== round($totalLiabilities + $totalEquity, 2)) {
            throw ValidationException::withMessages([
                'balance_sheet' => ['Balance sheet is not balanced (assets must equal liabilities + equity).'],
            ]);
        }

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
        ];
    }

    private function calculateNetProfit(?string $dateFrom, ?string $dateTo): float
    {
        $rows = JournalLine::query()
            ->select(['accounts.type'])
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
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
            ->whereIn('accounts.type', ['revenue', 'expense'])
            ->groupBy('accounts.type')
            ->get()
            ->keyBy('type');

        $revenueDebit = round((float) ($rows['revenue']->total_debit ?? 0), 2);
        $revenueCredit = round((float) ($rows['revenue']->total_credit ?? 0), 2);
        $expenseDebit = round((float) ($rows['expense']->total_debit ?? 0), 2);
        $expenseCredit = round((float) ($rows['expense']->total_credit ?? 0), 2);

        $totalRevenue = round($revenueCredit - $revenueDebit, 2);
        $totalExpense = round($expenseDebit - $expenseCredit, 2);

        return round($totalRevenue - $totalExpense, 2);
    }
}
