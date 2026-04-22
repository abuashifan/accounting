<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalLine;
use App\Domains\Accounting\Services\Concerns\ResolvesReportPeriod;

class CashFlowService
{
    use ResolvesReportPeriod;

    /**
     * Cash Flow (Arus Kas) - posted journals only.
     *
     * Simple assumption:
     * - Cash/bank accounts are asset accounts with code 1000/1100 OR name contains "kas"/"bank"
     *
     * Inflow  = SUM(debit)  on cash accounts
     * Outflow = SUM(credit) on cash accounts
     *
     * @return array{total_inflow: float, total_outflow: float, net_cash_flow: float}
     */
    public function getCashFlow(?int $periodId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        [$dateFrom, $dateTo] = $this->resolvePeriod($periodId, $dateFrom, $dateTo);

        $cashAccountIds = Account::query()
            ->where('type', 'asset')
            ->where(function ($q): void {
                $q->whereIn('code', ['1000', '1100'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%kas%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%bank%']);
            })
            ->pluck('id')
            ->all();

        if ($cashAccountIds === []) {
            return [
                'total_inflow' => 0.0,
                'total_outflow' => 0.0,
                'net_cash_flow' => 0.0,
            ];
        }

        $totals = JournalLine::query()
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_inflow')
            ->selectRaw('COALESCE(SUM(journal_lines.credit), 0) as total_outflow')
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
            ->whereIn('journal_lines.account_id', $cashAccountIds)
            ->first();

        $inflow = round((float) ($totals?->total_inflow ?? 0), 2);
        $outflow = round((float) ($totals?->total_outflow ?? 0), 2);

        return [
            'total_inflow' => $inflow,
            'total_outflow' => $outflow,
            'net_cash_flow' => round($inflow - $outflow, 2),
        ];
    }
}

