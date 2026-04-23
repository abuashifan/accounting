<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Actions\CheckPeriodAction;
use App\Domains\Accounting\Actions\CreateJournalAction;
use App\Domains\Accounting\Actions\ValidateJournalAction;
use App\Domains\Accounting\DTOs\InvoiceData;
use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Models\AccountingPeriod;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Item;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    private const KEY_ALLOW_ADMIN_EDIT_DELETE_POSTED = 'transactions.allow_admin_edit_delete_posted';

    public function __construct(
        private readonly JournalService $journalService,
        private readonly StockMovementService $stockMovementService,
        private readonly StockReversalService $stockReversalService,
        private readonly ValidateJournalAction $validateJournalAction,
        private readonly CheckPeriodAction $checkPeriodAction,
        private readonly CreateJournalAction $createJournalAction,
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

            $period = $this->resolvePeriodForDate($data->invoice_date);

            $journalEntry = $this->journalService->create(
                new JournalData(
                    date: $data->invoice_date,
                    description: $data->description ?? 'Invoice '.$data->invoice_no,
                    accounting_period_id: $period->id,
                    lines: [
                        new JournalLineData(account_id: $receivableAccount->id, debit: $data->amount, credit: 0),
                        new JournalLineData(account_id: $revenueAccount->id, debit: 0, credit: $data->amount),
                    ],
                ),
                reason: 'Invoice',
            );

            /** @var Invoice $invoice */
            $invoice = Invoice::query()->create([
                'invoice_no' => $data->invoice_no,
                'invoice_date' => $data->invoice_date,
                'description' => $data->description,
                'amount' => $data->amount,
                'paid_amount' => 0,
                'status' => 'unpaid',
                'posted_at' => $journalEntry->status === 'posted' ? now() : null,
                'journal_entry_id' => $journalEntry->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            return $invoice->load(['journalEntry.journalLines.account', 'payments']);
        });
    }

    /**
     * Create a sales invoice with item lines (inventory) and journal AR vs Revenue.
     * If `journals.auto_post=true`, it will also auto-post: reduce stock and add COGS vs Inventory lines.
     *
     * @param array{
     *   invoice_no:string,
     *   invoice_date:string,
     *   description?:string|null,
     *   lines:list<array{item_id:int,warehouse_id:int,quantity:float|int,unit_price:float|int}>
     * } $data
     */
    public function createSales(array $data): Invoice
    {
        return DB::transaction(function () use ($data): Invoice {
            $this->ensureInvoiceNumberIsUnique((string) $data['invoice_no']);

            $lines = $data['lines'] ?? [];
            if (! is_array($lines) || count($lines) < 1) {
                throw ValidationException::withMessages([
                    'lines' => ['At least one invoice line is required.'],
                ]);
            }

            $user = $this->resolveUserOrFail();
            $period = $this->resolvePeriodForDate((string) $data['invoice_date']);
            $receivableAccount = $this->resolveAccountByCode(
                (string) config('accounting.auto_journal.accounts.accounts_receivable')
            );

            // Aggregate revenue by revenue_account_id.
            $revenueByAccount = [];
            $linePayload = [];
            $invoiceTotal = 0.0;

            foreach ($lines as $idx => $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                $warehouseId = (int) ($line['warehouse_id'] ?? 0);
                $qty = (float) ($line['quantity'] ?? 0);
                $unitPrice = (float) ($line['unit_price'] ?? 0);

                if ($itemId <= 0 || $warehouseId <= 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}" => ['item_id and warehouse_id are required.'],
                    ]);
                }
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}.quantity" => ['Must be greater than zero.'],
                    ]);
                }
                if ($unitPrice < 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}.unit_price" => ['Must be greater than or equal to zero.'],
                    ]);
                }

                /** @var Item $item */
                $item = Item::query()->findOrFail($itemId);
                Warehouse::query()->findOrFail($warehouseId);

                if (($item->cost_method ?? 'average') !== 'average') {
                    throw ValidationException::withMessages([
                        "lines.{$idx}.item_id" => ['Only average cost is supported.'],
                    ]);
                }

                $qty = round($qty, 4);
                $unitPrice = round($unitPrice, 2);
                $lineTotal = round($qty * $unitPrice, 2);

                $invoiceTotal = round($invoiceTotal + $lineTotal, 2);

                $revAccId = (int) $item->revenue_account_id;
                $revenueByAccount[$revAccId] = round(($revenueByAccount[$revAccId] ?? 0) + $lineTotal, 2);

                $linePayload[] = [
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $journalLines = [
                new JournalLineData(account_id: (int) $receivableAccount->id, debit: $invoiceTotal, credit: 0),
            ];

            foreach ($revenueByAccount as $accountId => $amount) {
                $journalLines[] = new JournalLineData(account_id: (int) $accountId, debit: 0, credit: (float) $amount);
            }

            // Force-create as draft (we'll append COGS + stock effects at posting time).
            $journalEntry = $this->journalService->create(
                new JournalData(
                    date: (string) $data['invoice_date'],
                    description: (string) ($data['description'] ?? ('Sales invoice '.$data['invoice_no'])),
                    accounting_period_id: (int) $period->id,
                    lines: $journalLines,
                ),
                reason: 'Sales invoice',
                autoPostOverride: false,
            );

            /** @var Invoice $invoice */
            $invoice = Invoice::query()->create([
                'invoice_no' => (string) $data['invoice_no'],
                'invoice_date' => (string) $data['invoice_date'],
                'description' => $data['description'] ?? null,
                'amount' => $invoiceTotal,
                'paid_amount' => 0,
                'status' => 'unpaid',
                'posted_at' => null,
                'journal_entry_id' => $journalEntry->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($linePayload as $lp) {
                $lp['invoice_id'] = $invoice->id;
                InvoiceLine::query()->create($lp);
            }

            $autoPost = AppSetting::getBool('journals.auto_post', false);
            if ($autoPost) {
                $this->postSalesInvoiceInternal($invoice->id, $user, asSystem: true);
            }

            return $invoice->fresh(['invoiceLines.item', 'invoiceLines.warehouse', 'journalEntry.journalLines.account']);
        });
    }

    /**
     * Post a sales invoice: reduce stock + record movements + add COGS journal lines + post the journal.
     */
    public function postSales(int $invoiceId): Invoice
    {
        return DB::transaction(function () use ($invoiceId): Invoice {
            $user = $this->resolveUserOrFail();
            $this->postSalesInvoiceInternal($invoiceId, $user, asSystem: false);

            /** @var Invoice $invoice */
            $invoice = Invoice::query()->findOrFail($invoiceId);

            return $invoice->fresh(['invoiceLines.item', 'invoiceLines.warehouse', 'journalEntry.journalLines.account']);
        });
    }

    /**
     * Update a sales invoice (draft, or posted in dangerous mode).
     *
     * @param array{
     *   invoice_no:string,
     *   invoice_date:string,
     *   description?:string|null,
     *   lines:list<array{item_id:int,warehouse_id:int,quantity:float|int,unit_price:float|int}>
     * } $data
     */
    public function updateSales(int $invoiceId, array $data): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $data): Invoice {
            $user = $this->resolveUserOrFail();

            /** @var Invoice $invoice */
            $invoice = Invoice::query()
                ->with(['invoiceLines', 'payments', 'journalEntry'])
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            if ($invoice->voided_at !== null) {
                throw ValidationException::withMessages([
                    'voided_at' => ['Voided invoices cannot be updated.'],
                ]);
            }

            $wasPosted = $invoice->posted_at !== null;

            if ($wasPosted) {
                $this->ensureDangerousEditDeleteAllowedOrFail($user);

                if ($this->hasPostedDependencies($invoice)) {
                    throw ValidationException::withMessages([
                        'dependency' => ['Cannot edit/delete posted invoice because it has payments/returns.'],
                    ]);
                }

                $this->stockReversalService->reverseReference(
                    referenceType: 'invoice',
                    referenceId: (int) $invoice->id,
                    movementTypes: ['sale'],
                    deleteOriginalMovements: true,
                    reversalMovementType: null,
                );

                if ($invoice->journal_entry_id) {
                    $this->journalService->void((int) $invoice->journal_entry_id, 'Dangerous update posted invoice');
                }
            } else {
                if ((float) $invoice->paid_amount > 0 || $invoice->payments()->count() > 0) {
                    throw ValidationException::withMessages([
                        'paid_amount' => ['Cannot update an invoice that already has payments.'],
                    ]);
                }

                $journalEntry = $invoice->journalEntry;
                if (! $journalEntry || $journalEntry->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'journal_entry_id' => ['Only draft invoices can be updated.'],
                    ]);
                }
            }

            $newNo = (string) $data['invoice_no'];
            if ($newNo !== (string) $invoice->invoice_no) {
                if (Invoice::query()
                    ->where('invoice_no', $newNo)
                    ->where('id', '!=', $invoice->id)
                    ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'invoice_no' => ['Invoice number already exists.'],
                    ]);
                }
            }

            $lines = $data['lines'] ?? [];
            if (! is_array($lines) || count($lines) < 1) {
                throw ValidationException::withMessages([
                    'lines' => ['At least one invoice line is required.'],
                ]);
            }

            $period = $this->resolvePeriodForDate((string) $data['invoice_date']);
            $receivableAccount = $this->resolveAccountByCode((string) config('accounting.auto_journal.accounts.accounts_receivable'));

            $revenueByAccount = [];
            $linePayload = [];
            $invoiceTotal = 0.0;

            foreach ($lines as $idx => $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                $warehouseId = (int) ($line['warehouse_id'] ?? 0);
                $qty = (float) ($line['quantity'] ?? 0);
                $unitPrice = (float) ($line['unit_price'] ?? 0);

                if ($itemId <= 0 || $warehouseId <= 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}" => ['item_id and warehouse_id are required.'],
                    ]);
                }
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}.quantity" => ['Must be greater than zero.'],
                    ]);
                }
                if ($unitPrice < 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}.unit_price" => ['Must be greater than or equal to zero.'],
                    ]);
                }

                /** @var Item $item */
                $item = Item::query()->findOrFail($itemId);
                Warehouse::query()->findOrFail($warehouseId);

                if (($item->cost_method ?? 'average') !== 'average') {
                    throw ValidationException::withMessages([
                        "lines.{$idx}.item_id" => ['Only average cost is supported.'],
                    ]);
                }

                $qty = round($qty, 4);
                $unitPrice = round($unitPrice, 2);
                $lineTotal = round($qty * $unitPrice, 2);

                $invoiceTotal = round($invoiceTotal + $lineTotal, 2);

                $revAccId = (int) $item->revenue_account_id;
                $revenueByAccount[$revAccId] = round(($revenueByAccount[$revAccId] ?? 0) + $lineTotal, 2);

                $linePayload[] = [
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $journalLines = [
                new JournalLineData(account_id: (int) $receivableAccount->id, debit: $invoiceTotal, credit: 0),
            ];

            foreach ($revenueByAccount as $accountId => $amt) {
                $journalLines[] = new JournalLineData(account_id: (int) $accountId, debit: 0, credit: (float) $amt);
            }

            $journalData = new JournalData(
                date: (string) $data['invoice_date'],
                description: (string) ($data['description'] ?? ('Sales invoice '.$newNo)),
                accounting_period_id: (int) $period->id,
                lines: $journalLines,
            );

            if (! $wasPosted) {
                $this->journalService->update((int) $invoice->journal_entry_id, $journalData, 'Update sales invoice');
            } else {
                $newJournal = $this->journalService->create($journalData, reason: 'Dangerous update sales invoice (repost)', autoPostOverride: false);
                $invoice->forceFill([
                    'journal_entry_id' => (int) $newJournal->id,
                ]);
            }

            $invoice->forceFill([
                'invoice_no' => $newNo,
                'invoice_date' => (string) $data['invoice_date'],
                'description' => $data['description'] ?? null,
                'amount' => $invoiceTotal,
                'paid_amount' => 0,
                'status' => 'unpaid',
                'posted_at' => $wasPosted ? null : $invoice->posted_at,
                'updated_by' => $user->id,
            ])->save();

            InvoiceLine::query()->where('invoice_id', $invoice->id)->delete();
            foreach ($linePayload as $lp) {
                $lp['invoice_id'] = $invoice->id;
                InvoiceLine::query()->create($lp);
            }

            if ($wasPosted) {
                $this->postSalesInvoiceInternal((int) $invoice->id, $user, asSystem: false);
            }

            return $invoice->fresh(['invoiceLines.item', 'invoiceLines.warehouse', 'journalEntry.journalLines.account', 'payments']);
        });
    }

    public function deleteSales(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId): void {
            $user = $this->resolveUserOrFail();

            /** @var Invoice $invoice */
            $invoice = Invoice::query()
                ->with(['payments', 'journalEntry'])
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            if ($invoice->voided_at !== null) {
                throw ValidationException::withMessages([
                    'voided_at' => ['Voided invoices cannot be deleted.'],
                ]);
            }

            if ($invoice->posted_at !== null) {
                $this->ensureDangerousEditDeleteAllowedOrFail($user);

                if ($this->hasPostedDependencies($invoice)) {
                    throw ValidationException::withMessages([
                        'dependency' => ['Cannot edit/delete posted invoice because it has payments/returns.'],
                    ]);
                }

                $this->stockReversalService->reverseReference(
                    referenceType: 'invoice',
                    referenceId: (int) $invoice->id,
                    movementTypes: ['sale'],
                    deleteOriginalMovements: true,
                    reversalMovementType: null,
                );
            } else {
                if ((float) $invoice->paid_amount > 0 || $invoice->payments()->count() > 0) {
                    throw ValidationException::withMessages([
                        'paid_amount' => ['Cannot delete an invoice that already has payments.'],
                    ]);
                }

                $journalEntry = $invoice->journalEntry;
                if (! $journalEntry || $journalEntry->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'journal_entry_id' => ['Only draft invoices can be deleted.'],
                    ]);
                }
            }

            if ($invoice->journal_entry_id) {
                $this->journalService->void((int) $invoice->journal_entry_id, $invoice->posted_at !== null ? 'Dangerous delete posted invoice' : 'Delete invoice');
            }

            $invoice->forceFill([
                'updated_by' => $user->id,
            ])->save();

            $invoice->delete();
        });
    }

    public function voidSales(int $invoiceId, ?string $reason = null): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $reason): Invoice {
            $user = $this->resolveUserOrFail();

            /** @var Invoice $invoice */
            $invoice = Invoice::query()
                ->with(['invoiceLines', 'payments', 'journalEntry'])
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            if ($invoice->voided_at !== null) {
                throw ValidationException::withMessages([
                    'voided_at' => ['Invoice is already voided.'],
                ]);
            }

            if ($this->hasPostedDependencies($invoice)) {
                throw ValidationException::withMessages([
                    'dependency' => ['Cannot void posted invoice because it has payments/returns.'],
                ]);
            }

            if ($invoice->posted_at !== null) {
                $this->stockReversalService->reverseReference(
                    referenceType: 'invoice',
                    referenceId: (int) $invoice->id,
                    movementTypes: ['sale'],
                    deleteOriginalMovements: false,
                    reversalMovementType: 'void_invoice',
                );
            }

            if ($invoice->journal_entry_id) {
                $this->journalService->void((int) $invoice->journal_entry_id, $reason ?: 'Void invoice');
            }

            $invoice->forceFill([
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => $user->id,
                'updated_by' => $user->id,
            ])->save();

            return $invoice->fresh(['invoiceLines.item', 'invoiceLines.warehouse', 'journalEntry.journalLines.account', 'payments']);
        });
    }

    private function postSalesInvoiceInternal(int $invoiceId, User $user, bool $asSystem): void
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::query()
            ->with(['invoiceLines', 'journalEntry.accountingPeriod', 'journalEntry.journalLines'])
            ->lockForUpdate()
            ->findOrFail($invoiceId);

        if ($invoice->posted_at !== null) {
            throw ValidationException::withMessages([
                'posted_at' => ['Invoice is already posted.'],
            ]);
        }

        if ($invoice->invoiceLines->count() < 1) {
            throw ValidationException::withMessages([
                'lines' => ['This invoice has no item lines.'],
            ]);
        }

        $journalEntry = $invoice->journalEntry;
        if (! $journalEntry || $journalEntry->status !== 'draft') {
            throw ValidationException::withMessages([
                'journal_entry_id' => ['Invoice journal must be draft before posting.'],
            ]);
        }

        // Reduce stock and compute COGS per account.
        $cogsByAccount = [];
        $invCreditByAccount = [];

        foreach ($invoice->invoiceLines as $line) {
            $itemId = (int) $line->item_id;
            $warehouseId = (int) $line->warehouse_id;
            $qtyOut = round((float) $line->quantity, 4);

            /** @var Item $item */
            $item = Item::query()->findOrFail($itemId);
            Warehouse::query()->findOrFail($warehouseId);

            /** @var StockBalance $balance */
            $balance = StockBalance::query()
                ->where('item_id', $itemId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->firstOrCreate([
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                ], [
                    'quantity' => 0,
                    'avg_cost' => 0,
                ]);

            $oldQty = round((float) $balance->quantity, 4);
            $avgCost = round((float) $balance->avg_cost, 6);

            $allowNegative = AppSetting::getBool('inventory.allow_negative_stock', false);
            if (! $allowNegative && round($oldQty - $qtyOut, 4) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stock cannot be negative.'],
                ]);
            }

            $newQty = round($oldQty - $qtyOut, 4);
            $balance->forceFill([
                'quantity' => $newQty,
                'avg_cost' => $avgCost,
            ])->save();

            $this->stockMovementService->record(
                date: (string) $invoice->invoice_date?->format('Y-m-d'),
                itemId: $itemId,
                warehouseId: $warehouseId,
                type: 'sale',
                qtyIn: 0,
                qtyOut: $qtyOut,
                unitCost: $avgCost,
                referenceType: 'invoice',
                referenceId: (int) $invoice->id,
            );

            $cogs = round($qtyOut * $avgCost, 2);
            $cogsAccId = (int) $item->cogs_account_id;
            $invAccId = (int) $item->inventory_account_id;

            $cogsByAccount[$cogsAccId] = round(($cogsByAccount[$cogsAccId] ?? 0) + $cogs, 2);
            $invCreditByAccount[$invAccId] = round(($invCreditByAccount[$invAccId] ?? 0) + $cogs, 2);
        }

        // Rebuild full journal: keep AR+Revenue from invoice, and add COGS+Inventory credits.
        $invoice->loadMissing(['invoiceLines.item', 'journalEntry.accountingPeriod']);

        $revenueByAccount = [];
        foreach ($invoice->invoiceLines as $line) {
            $item = $line->item;
            if (! $item) {
                continue;
            }
            $revAccId = (int) $item->revenue_account_id;
            $revenueByAccount[$revAccId] = round(($revenueByAccount[$revAccId] ?? 0) + (float) $line->line_total, 2);
        }

        $fullLines = [
            new JournalLineData(account_id: $this->resolveAccountByCode((string) config('accounting.auto_journal.accounts.accounts_receivable'))->id, debit: (float) $invoice->amount, credit: 0),
        ];

        foreach ($revenueByAccount as $accountId => $amt) {
            $fullLines[] = new JournalLineData(account_id: (int) $accountId, debit: 0, credit: (float) $amt);
        }

        foreach ($cogsByAccount as $accountId => $amt) {
            $fullLines[] = new JournalLineData(account_id: (int) $accountId, debit: (float) $amt, credit: 0);
        }

        foreach ($invCreditByAccount as $accountId => $amt) {
            $fullLines[] = new JournalLineData(account_id: (int) $accountId, debit: 0, credit: (float) $amt);
        }

        $data = new JournalData(
            date: (string) $invoice->invoice_date?->format('Y-m-d'),
            description: (string) ($invoice->description ?? ('Sales invoice '.$invoice->invoice_no)),
            accounting_period_id: (int) $journalEntry->accounting_period_id,
            lines: $fullLines,
        );

        if ($asSystem) {
            // No `journal.update` permission required for auto-post flows.
            $this->validateJournalAction->execute($data);
            $this->checkPeriodAction->execute($journalEntry->accountingPeriod, $user);
            $updatedJournal = $this->createJournalAction->execute($data, $user, $journalEntry);

            $this->journalService->postAsSystem($updatedJournal, $user, 'Auto-post sales invoice');
        } else {
            $this->journalService->update((int) $journalEntry->id, $data, 'Post sales invoice');
            $this->journalService->post((int) $journalEntry->id, 'Post sales invoice');
        }

        $invoice->forceFill([
            'posted_at' => now(),
            'updated_by' => $user->id,
        ])->save();
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
                'invoice_date' => ['No accounting period covers the given invoice date.'],
            ]);
        }

        return $period;
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

    private function ensureDangerousEditDeleteAllowedOrFail(User $user): void
    {
        if (! AppSetting::getBool(self::KEY_ALLOW_ADMIN_EDIT_DELETE_POSTED, false)) {
            throw new AuthorizationException('Editing/deleting posted transactions is disabled. Use VOID/RETURN instead.');
        }

        Gate::forUser($user)->authorize('transactions.override_posted_edit_delete');
    }

    private function hasPostedDependencies(Invoice $invoice): bool
    {
        if ((float) $invoice->paid_amount > 0 || $invoice->payments()->count() > 0) {
            return true;
        }

        return SalesReturn::query()->where('invoice_id', (int) $invoice->id)->exists();
    }
}
