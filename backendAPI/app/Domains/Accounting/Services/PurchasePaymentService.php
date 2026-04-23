<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\DTOs\PurchasePaymentData;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\PurchaseInvoice;
use App\Models\PurchasePayment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasePaymentService
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    public function record(PurchasePaymentData $data): PurchasePayment
    {
        return DB::transaction(function () use ($data): PurchasePayment {
            $this->validateAmount($data->amount, 'amount');

            $user = $this->resolveUserOrFail();

            /** @var PurchaseInvoice $invoice */
            $invoice = PurchaseInvoice::query()->lockForUpdate()->findOrFail($data->purchase_invoice_id);

            if (PurchasePayment::query()->where('payment_no', $data->payment_no)->exists()) {
                throw ValidationException::withMessages([
                    'payment_no' => ['Payment number already exists.'],
                ]);
            }

            $this->ensureNotOverpaid($invoice, $data->amount);

            $payable = $this->resolveAccountByCode((string) config('accounting.auto_journal.accounts.accounts_payable'));
            $creditAccount = Account::query()->findOrFail((int) $data->credit_account_id);

            $period = $this->resolvePeriodForDate($data->payment_date);

            $journalEntry = $this->journalService->create(
                new JournalData(
                    date: $data->payment_date,
                    description: $data->description ?? 'Purchase payment '.$data->payment_no,
                    accounting_period_id: (int) $period->id,
                    lines: [
                        new JournalLineData(account_id: (int) $payable->id, debit: $data->amount, credit: 0),
                        new JournalLineData(account_id: (int) $creditAccount->id, debit: 0, credit: $data->amount),
                    ],
                ),
                reason: 'Purchase payment',
            );

            /** @var PurchasePayment $payment */
            $payment = PurchasePayment::query()->create([
                'payment_no' => $data->payment_no,
                'purchase_invoice_id' => $invoice->id,
                'payment_date' => $data->payment_date,
                'description' => $data->description,
                'amount' => $data->amount,
                'journal_entry_id' => (int) $journalEntry->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $paidAmount = round((float) $invoice->paid_amount + $data->amount, 2);

            $invoice->forceFill([
                'paid_amount' => $paidAmount,
                'status' => $paidAmount >= round((float) $invoice->amount, 2) ? 'paid' : 'partial',
                'updated_by' => $user->id,
            ])->save();

            return $payment->load(['purchaseInvoice', 'journalEntry.journalLines.account']);
        });
    }

    /**
     * Update a purchase payment (requires journal to be draft).
     *
     * @param array{
     *   payment_no:string,
     *   payment_date:string,
     *   amount:float|int,
     *   credit_account_id:int,
     *   description?:string|null
     * } $data
     */
    public function update(int $paymentId, array $data): PurchasePayment
    {
        return DB::transaction(function () use ($paymentId, $data): PurchasePayment {
            $this->validateAmount((float) $data['amount'], 'amount');
            $user = $this->resolveUserOrFail();

            /** @var PurchasePayment $payment */
            $payment = PurchasePayment::query()
                ->with(['purchaseInvoice', 'journalEntry'])
                ->lockForUpdate()
                ->findOrFail($paymentId);

            $journalEntry = $payment->journalEntry;
            if (! $journalEntry || $journalEntry->status !== 'draft') {
                throw ValidationException::withMessages([
                    'journal_entry_id' => ['Only draft payments can be updated.'],
                ]);
            }

            /** @var PurchaseInvoice $invoice */
            $invoice = PurchaseInvoice::query()->lockForUpdate()->findOrFail((int) $payment->purchase_invoice_id);

            $newNo = (string) $data['payment_no'];
            if ($newNo !== (string) $payment->payment_no) {
                if (PurchasePayment::query()
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

            $payable = $this->resolveAccountByCode((string) config('accounting.auto_journal.accounts.accounts_payable'));
            $creditAccount = Account::query()->findOrFail((int) $data['credit_account_id']);
            $period = $this->resolvePeriodForDate((string) $data['payment_date']);

            $this->journalService->update(
                (int) $journalEntry->id,
                new JournalData(
                    date: (string) $data['payment_date'],
                    description: (string) (($data['description'] ?? null) ?: ('Purchase payment '.$newNo)),
                    accounting_period_id: (int) $period->id,
                    lines: [
                        new JournalLineData(account_id: (int) $payable->id, debit: $newAmount, credit: 0),
                        new JournalLineData(account_id: (int) $creditAccount->id, debit: 0, credit: $newAmount),
                    ],
                ),
                reason: 'Update purchase payment',
            );

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

            return $payment->fresh(['purchaseInvoice', 'journalEntry.journalLines.account']);
        });
    }

    public function delete(int $paymentId): void
    {
        DB::transaction(function () use ($paymentId): void {
            $user = $this->resolveUserOrFail();

            /** @var PurchasePayment $payment */
            $payment = PurchasePayment::query()
                ->with(['purchaseInvoice', 'journalEntry'])
                ->lockForUpdate()
                ->findOrFail($paymentId);

            /** @var PurchaseInvoice $invoice */
            $invoice = PurchaseInvoice::query()->lockForUpdate()->findOrFail((int) $payment->purchase_invoice_id);

            $journalEntry = $payment->journalEntry;
            if ($journalEntry) {
                $this->journalService->void((int) $journalEntry->id, 'Delete purchase payment');
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

    private function ensureNotOverpaid(PurchaseInvoice $invoice, float $amount): void
    {
        if (round((float) $invoice->paid_amount + $amount, 2) > round((float) $invoice->amount, 2)) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount exceeds outstanding purchase invoice balance.'],
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
}
