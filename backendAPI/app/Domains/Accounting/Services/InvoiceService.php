<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\DTOs\InvoiceData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private readonly AutoPostingJournalService $autoPostingJournalService,
    ) {}

    public function create(InvoiceData $data): Invoice
    {
        return DB::transaction(function () use ($data): Invoice {
            $this->validateAmount($data->amount, 'amount');
            $this->ensureInvoiceNumberIsUnique($data->invoice_no);

            $user = $this->resolveUserOrFail();
            $receivableAccount = $this->resolveAccountByCode(
                (string) config('accounting.auto_journal.accounts.accounts_receivable')
            );
            $revenueAccount = $this->resolveAccountByCode(
                (string) config('accounting.auto_journal.accounts.revenue')
            );

            $journalEntry = $this->autoPostingJournalService->createPostedJournal(
                date: $data->invoice_date,
                description: $data->description ?? 'Auto journal invoice '.$data->invoice_no,
                lines: [
                    new JournalLineData(account_id: $receivableAccount->id, debit: $data->amount, credit: 0),
                    new JournalLineData(account_id: $revenueAccount->id, debit: 0, credit: $data->amount),
                ],
            );

            /** @var Invoice $invoice */
            $invoice = Invoice::query()->create([
                'invoice_no' => $data->invoice_no,
                'invoice_date' => $data->invoice_date,
                'description' => $data->description,
                'amount' => $data->amount,
                'paid_amount' => 0,
                'status' => 'unpaid',
                'journal_entry_id' => $journalEntry->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            return $invoice->load(['journalEntry.journalLines.account', 'payments']);
        });
    }

    private function resolveUserOrFail(): User
    {
        $requestUser = request()?->user();

        if ($requestUser instanceof User) {
            return $requestUser;
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Unauthenticated.');
        }

        return $user;
    }

    private function validateAmount(float $amount, string $field): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                $field => ['Amount must be greater than zero.'],
            ]);
        }
    }

    private function ensureInvoiceNumberIsUnique(string $invoiceNo): void
    {
        if (Invoice::query()->where('invoice_no', $invoiceNo)->exists()) {
            throw ValidationException::withMessages([
                'invoice_no' => ['Invoice number already exists.'],
            ]);
        }
    }

    private function resolveAccountByCode(string $accountCode): Account
    {
        /** @var Account|null $account */
        $account = Account::query()->where('code', $accountCode)->first();

        if ($account instanceof Account) {
            return $account;
        }

        throw (new ModelNotFoundException)->setModel(Account::class, [$accountCode]);
    }
}
