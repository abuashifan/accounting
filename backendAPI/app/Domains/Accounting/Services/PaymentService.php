<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\DTOs\PaymentData;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly AutoPostingJournalService $autoPostingJournalService,
    ) {}

    public function record(PaymentData $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $this->validateAmount($data->amount, 'amount');

            $user = $this->resolveUserOrFail();
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($data->invoice_id);

            $this->ensurePaymentNumberIsUnique($data->payment_no);
            $this->ensureNotOverpaid($invoice, $data->amount);

            $cashAccount = $this->resolveAccountByCode(
                (string) config('accounting.auto_journal.accounts.cash')
            );
            $receivableAccount = $this->resolveAccountByCode(
                (string) config('accounting.auto_journal.accounts.accounts_receivable')
            );

            $journalEntry = $this->autoPostingJournalService->createPostedJournal(
                date: $data->payment_date,
                description: $data->description ?? 'Auto journal payment '.$data->payment_no,
                lines: [
                    new JournalLineData(account_id: $cashAccount->id, debit: $data->amount, credit: 0),
                    new JournalLineData(account_id: $receivableAccount->id, debit: 0, credit: $data->amount),
                ],
            );

            /** @var Payment $payment */
            $payment = Payment::query()->create([
                'payment_no' => $data->payment_no,
                'invoice_id' => $invoice->id,
                'payment_date' => $data->payment_date,
                'description' => $data->description,
                'amount' => $data->amount,
                'journal_entry_id' => $journalEntry->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $paidAmount = round((float) $invoice->paid_amount + $data->amount, 2);

            $invoice->forceFill([
                'paid_amount' => $paidAmount,
                'status' => $paidAmount >= round((float) $invoice->amount, 2) ? 'paid' : 'partial',
                'updated_by' => $user->id,
            ])->save();

            return $payment->load(['invoice', 'journalEntry.journalLines.account']);
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

    private function ensurePaymentNumberIsUnique(string $paymentNo): void
    {
        if (Payment::query()->where('payment_no', $paymentNo)->exists()) {
            throw ValidationException::withMessages([
                'payment_no' => ['Payment number already exists.'],
            ]);
        }
    }

    private function ensureNotOverpaid(Invoice $invoice, float $amount): void
    {
        if (round((float) $invoice->paid_amount + $amount, 2) > round((float) $invoice->amount, 2)) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount exceeds outstanding invoice balance.'],
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
