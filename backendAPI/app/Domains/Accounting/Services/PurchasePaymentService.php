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

