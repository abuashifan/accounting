<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\DTOs\PaymentData;
use App\Models\AccountingPeriod;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    private const KEY_ALLOW_ADMIN_EDIT_DELETE_POSTED = 'transactions.allow_admin_edit_delete_posted';

    public function __construct(
        private readonly JournalService $journalService,
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

            $period = $this->resolvePeriodForDate($data->payment_date);

            $journalEntry = $this->journalService->create(
                new JournalData(
                    date: $data->payment_date,
                    description: $data->description ?? 'Payment '.$data->payment_no,
                    accounting_period_id: $period->id,
                    lines: [
                        new JournalLineData(account_id: $cashAccount->id, debit: $data->amount, credit: 0),
                        new JournalLineData(account_id: $receivableAccount->id, debit: 0, credit: $data->amount),
                    ],
                ),
                reason: 'Payment',
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

    /**
     * Update a sales payment (requires journal to be draft, unless dangerous mode is enabled for posted journal).
     *
     * @param array{
     *   payment_no:string,
     *   payment_date:string,
     *   amount:float|int,
     *   description?:string|null
     * } $data
     */
    public function update(int $paymentId, array $data): Payment
    {
        return DB::transaction(function () use ($paymentId, $data): Payment {
            $this->validateAmount((float) $data['amount'], 'amount');
            $user = $this->resolveUserOrFail();

            /** @var Payment $payment */
            $payment = Payment::query()
                ->with(['invoice', 'journalEntry'])
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if ($payment->voided_at !== null) {
                throw ValidationException::withMessages([
                    'voided_at' => ['Voided payments cannot be updated.'],
                ]);
            }

            $journalEntry = $payment->journalEntry;
            if (! $journalEntry) {
                throw ValidationException::withMessages([
                    'journal_entry_id' => ['Payment has no journal entry.'],
                ]);
            }

            $isPosted = $journalEntry->status === 'posted';
            $isDraft = $journalEntry->status === 'draft';

            if (! $isDraft && ! $isPosted) {
                throw ValidationException::withMessages([
                    'journal_entry_id' => ['Only draft/posted payments can be updated.'],
                ]);
            }

            if ($isPosted) {
                $this->ensureDangerousEditDeleteAllowedOrFail($user);
            }

            /** @var Invoice $invoice */
            $invoice = Invoice::query()->lockForUpdate()->findOrFail((int) $payment->invoice_id);

            $newNo = (string) $data['payment_no'];
            if ($newNo !== (string) $payment->payment_no) {
                if (Payment::query()
                    ->where('payment_no', $newNo)
                    ->where('id', '!=', $payment->id)
                    ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'payment_no' => ['Payment number already exists.'],
                    ]);
                }
            }

            $newAmount = round((float) $data['amount'], 2);
            $oldAmount = round((float) $payment->amount, 2);
            $delta = round($newAmount - $oldAmount, 2);

            if ($delta > 0) {
                $this->ensureNotOverpaid($invoice, $delta);
            }

            $cashAccount = $this->resolveAccountByCode((string) config('accounting.auto_journal.accounts.cash'));
            $receivableAccount = $this->resolveAccountByCode((string) config('accounting.auto_journal.accounts.accounts_receivable'));
            $period = $this->resolvePeriodForDate((string) $data['payment_date']);

            $journalData = new JournalData(
                date: (string) $data['payment_date'],
                description: (string) (($data['description'] ?? null) ?: ('Payment '.$newNo)),
                accounting_period_id: (int) $period->id,
                lines: [
                    new JournalLineData(account_id: (int) $cashAccount->id, debit: $newAmount, credit: 0),
                    new JournalLineData(account_id: (int) $receivableAccount->id, debit: 0, credit: $newAmount),
                ],
            );

            if ($isDraft) {
                $this->journalService->update((int) $journalEntry->id, $journalData, 'Update payment');
            } else {
                $this->journalService->void((int) $journalEntry->id, 'Dangerous update posted payment');

                $newJournal = $this->journalService->create(
                    $journalData,
                    reason: 'Dangerous update payment (repost)',
                    autoPostOverride: true,
                );

                $payment->forceFill([
                    'journal_entry_id' => (int) $newJournal->id,
                ]);
            }

            $payment->forceFill([
                'payment_no' => $newNo,
                'payment_date' => (string) $data['payment_date'],
                'amount' => $newAmount,
                'description' => $data['description'] ?? null,
                'updated_by' => $user->id,
            ])->save();

            $newPaid = round((float) $invoice->paid_amount + $delta, 2);
            $newPaid = max(0.0, $newPaid);

            $invoice->forceFill([
                'paid_amount' => $newPaid,
                'status' => $newPaid >= round((float) $invoice->amount, 2) ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid'),
                'updated_by' => $user->id,
            ])->save();

            return $payment->fresh(['invoice', 'journalEntry.journalLines.account']);
        });
    }

    public function delete(int $paymentId): void
    {
        DB::transaction(function () use ($paymentId): void {
            $user = $this->resolveUserOrFail();

            /** @var Payment $payment */
            $payment = Payment::query()
                ->with(['invoice', 'journalEntry'])
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if ($payment->voided_at !== null) {
                throw ValidationException::withMessages([
                    'voided_at' => ['Voided payments cannot be deleted.'],
                ]);
            }

            /** @var Invoice $invoice */
            $invoice = Invoice::query()->lockForUpdate()->findOrFail((int) $payment->invoice_id);

            $journalEntry = $payment->journalEntry;
            if ($journalEntry && $journalEntry->status === 'posted') {
                $this->ensureDangerousEditDeleteAllowedOrFail($user);
            }

            if ($payment->journal_entry_id) {
                $this->journalService->void((int) $payment->journal_entry_id, $journalEntry && $journalEntry->status === 'posted' ? 'Dangerous delete posted payment' : 'Delete payment');
            }

            $newPaid = round((float) $invoice->paid_amount - round((float) $payment->amount, 2), 2);
            $newPaid = max(0.0, $newPaid);

            $invoice->forceFill([
                'paid_amount' => $newPaid,
                'status' => $newPaid >= round((float) $invoice->amount, 2) ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid'),
                'updated_by' => $user->id,
            ])->save();

            $payment->delete();
        });
    }

    public function void(int $paymentId, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($paymentId, $reason): Payment {
            $user = $this->resolveUserOrFail();

            /** @var Payment $payment */
            $payment = Payment::query()
                ->with(['invoice', 'journalEntry'])
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if ($payment->voided_at !== null) {
                throw ValidationException::withMessages([
                    'voided_at' => ['Payment is already voided.'],
                ]);
            }

            /** @var Invoice $invoice */
            $invoice = Invoice::query()->lockForUpdate()->findOrFail((int) $payment->invoice_id);

            if ($payment->journal_entry_id) {
                $this->journalService->void((int) $payment->journal_entry_id, $reason ?: 'Void payment');
            }

            $newPaid = round((float) $invoice->paid_amount - round((float) $payment->amount, 2), 2);
            $newPaid = max(0.0, $newPaid);

            $invoice->forceFill([
                'paid_amount' => $newPaid,
                'status' => $newPaid >= round((float) $invoice->amount, 2) ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid'),
                'updated_by' => $user->id,
            ])->save();

            $payment->forceFill([
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => $user->id,
                'updated_by' => $user->id,
            ])->save();

            return $payment->fresh(['invoice', 'journalEntry.journalLines.account']);
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

    private function resolvePeriodForDate(string $date): AccountingPeriod
    {
        $period = AccountingPeriod::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('start_date')
            ->first();

        if (! $period instanceof AccountingPeriod) {
            throw ValidationException::withMessages([
                'payment_date' => ['No accounting period covers the given payment date.'],
            ]);
        }

        return $period;
    }

    private function ensureDangerousEditDeleteAllowedOrFail(User $user): void
    {
        if (! AppSetting::getBool(self::KEY_ALLOW_ADMIN_EDIT_DELETE_POSTED, false)) {
            throw new AuthorizationException('Editing/deleting posted transactions is disabled. Use VOID/RETURN instead.');
        }

        Gate::forUser($user)->authorize('transactions.override_posted_edit_delete');
    }
}
