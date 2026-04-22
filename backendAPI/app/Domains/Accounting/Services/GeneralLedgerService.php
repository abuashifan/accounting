<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalLine;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GeneralLedgerService
{
    /**
     * General Ledger (Buku Besar) - posted journals only.
     *
     * Running balance rule:
     * balance = previous_balance + debit - credit
     *
     * @return list<array{
     *   date: string,
     *   journal_id: int,
     *   description: string|null,
     *   debit: float,
     *   credit: float,
     *   balance: float
     * }>
     */
    public function getLedger(int $accountId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $lines = JournalLine::query()
            ->select([
                'journal_lines.debit',
                'journal_lines.credit',
                'journal_lines.description as line_description',
                'journal_entries.id as journal_id',
                'journal_entries.date as journal_date',
                'journal_entries.description as journal_description',
            ])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $accountId)
            ->where('journal_entries.status', 'posted')
            ->when($dateFrom, fn ($q) => $q->where('journal_entries.date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('journal_entries.date', '<=', $dateTo))
            ->orderBy('journal_entries.date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.id')
            ->get();

        $runningBalance = 0.0;

        return $lines->map(function (object $line) use (&$runningBalance): array {
            $debit = round((float) $line->debit, 2);
            $credit = round((float) $line->credit, 2);
            $runningBalance = round($runningBalance + ($debit - $credit), 2);

            return [
                'date' => $this->toDateString($line->journal_date),
                'journal_id' => (int) $line->journal_id,
                'description' => $line->journal_description ?? $line->line_description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
            ];
        })->values()->all();
    }

    /**
     * Kept for internal/debug UI which needs opening/closing details.
     *
     * @return array<string, mixed>
     */
    public function getLedgerDetailed(int $accountId, string $startDate, string $endDate): array
    {
        [$startDate, $endDate] = $this->normalizePeriod($startDate, $endDate);

        /** @var Account $account */
        $account = Account::query()->findOrFail($accountId);

        $openingTotals = JournalLine::query()
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as debit_total')
            ->selectRaw('COALESCE(SUM(journal_lines.credit), 0) as credit_total')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $account->id)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.date', '<', $startDate)
            ->first();

        $openingDebit = round((float) ($openingTotals?->debit_total ?? 0), 2);
        $openingCredit = round((float) ($openingTotals?->credit_total ?? 0), 2);
        $openingBalance = round($this->toSignedBalanceDelta($account, $openingDebit, $openingCredit), 2);

        $lines = JournalLine::query()
            ->select([
                'journal_lines.id',
                'journal_lines.debit',
                'journal_lines.credit',
                'journal_lines.description as line_description',
                'journal_entries.id as journal_entry_id',
                'journal_entries.journal_no',
                'journal_entries.date',
                'journal_entries.description as journal_description',
            ])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $account->id)
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->orderBy('journal_entries.date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.id')
            ->get();

        $runningBalance = $openingBalance;

        $entries = $lines->map(function (object $line) use ($account, &$runningBalance): array {
            $debit = round((float) $line->debit, 2);
            $credit = round((float) $line->credit, 2);
            $runningBalance = round($runningBalance + $this->toSignedBalanceDelta($account, $debit, $credit), 2);

            return [
                'journal_entry_id' => (int) $line->journal_entry_id,
                'journal_line_id' => (int) $line->id,
                'journal_no' => $line->journal_no,
                'date' => $line->date,
                'description' => $line->journal_description ?? $line->line_description,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $runningBalance,
            ];
        })->values()->all();

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'opening_balance' => $openingBalance,
            'opening_debit' => $openingDebit,
            'opening_credit' => $openingCredit,
            'total_debit' => round($lines->sum(fn (object $line): float => (float) $line->debit), 2),
            'total_credit' => round($lines->sum(fn (object $line): float => (float) $line->credit), 2),
            'closing_balance' => $runningBalance,
            'entries' => $entries,
        ];
    }

    /**
     * @return Collection<int, array{id:int,code:string,name:string,type:string}>
     */
    public function getSelectableAccounts(): Collection
    {
        return Account::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (Account $account): array => [
                'id' => (int) $account->id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'type' => (string) $account->type,
            ]);
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

    private function toSignedBalanceDelta(Account $account, float $debit, float $credit): float
    {
        if (in_array($account->type, ['asset', 'expense'], true)) {
            return $debit - $credit;
        }

        return $credit - $debit;
    }

    private function toDateString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $string = (string) $value;

        return strlen($string) >= 10 ? substr($string, 0, 10) : $string;
    }
}
