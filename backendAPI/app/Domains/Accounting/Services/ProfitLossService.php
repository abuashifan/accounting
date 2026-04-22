<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\JournalLine;
use App\Domains\Accounting\Services\Concerns\ResolvesReportPeriod;

class ProfitLossService
{
    use ResolvesReportPeriod;

    /**
     * Profit & Loss - posted journals only.
     *
     * Revenue amount = SUM(credit) - SUM(debit)
     * Expense amount = SUM(debit) - SUM(credit)
     *
     * @return array{
     *   total_revenue: float,
     *   total_expense: float,
     *   net_profit: float
     * }
     */
    public function getProfitLoss(?int $periodId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        [$dateFrom, $dateTo] = $this->resolvePeriod($periodId, $dateFrom, $dateTo);

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

        return [
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_profit' => round($totalRevenue - $totalExpense, 2),
        ];
    }
}

