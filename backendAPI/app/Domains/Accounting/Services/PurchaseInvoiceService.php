<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AppSetting;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseReturn;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceService
{
    private const KEY_ALLOW_ADMIN_EDIT_DELETE_POSTED = 'transactions.allow_admin_edit_delete_posted';

    public function __construct(
        private readonly JournalService $journalService,
        private readonly StockMovementService $stockMovementService,
        private readonly StockReversalService $stockReversalService,
    ) {}

    /**
     * Create purchase invoice (faktur pembelian). When `journals.auto_post=true`, it will post immediately:
     * Persediaan (D) - Utang (C) and stock will increase.
     *
     * @param array{
     *   invoice_no:string,
     *   invoice_date:string,
     *   vendor_id?:int|null,
     *   description?:string|null,
     *   lines:list<array{item_id:int,warehouse_id:int,quantity:float|int,unit_cost:float|int}>
     * } $data
     */
    public function create(array $data): PurchaseInvoice
    {
        return DB::transaction(function () use ($data): PurchaseInvoice {
            $user = $this->resolveUserOrFail();

            if (PurchaseInvoice::query()->where('invoice_no', (string) $data['invoice_no'])->exists()) {
                throw ValidationException::withMessages([
                    'invoice_no' => ['Invoice number already exists.'],
                ]);
            }

            $period = $this->resolvePeriodForDate((string) $data['invoice_date']);
            $payable = $this->resolveAccountByCode((string) config('accounting.auto_journal.accounts.accounts_payable'));

            $lines = $data['lines'] ?? [];
            if (! is_array($lines) || count($lines) < 1) {
                throw ValidationException::withMessages([
                    'lines' => ['At least one invoice line is required.'],
                ]);
            }

            $vendorId = (int) ($data['vendor_id'] ?? 0);
            if ($vendorId > 0) {
                Vendor::query()->findOrFail($vendorId);
            }

            $inventoryByAccount = [];
            $linePayload = [];
            $invoiceTotal = 0.0;

            foreach ($lines as $idx => $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                $warehouseId = (int) ($line['warehouse_id'] ?? 0);
                $qty = (float) ($line['quantity'] ?? 0);
                $unitCost = (float) ($line['unit_cost'] ?? 0);

                if ($itemId <= 0 || $warehouseId <= 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}" => ['item_id and warehouse_id are required.'],
                    ]);
                }
                if ($qty <= 0 || $unitCost <= 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}" => ['quantity and unit_cost must be greater than zero.'],
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
                $unitCost = round($unitCost, 6);
                $lineTotal = round($qty * $unitCost, 2);

                $invoiceTotal = round($invoiceTotal + $lineTotal, 2);

                $invAccId = (int) $item->inventory_account_id;
                $inventoryByAccount[$invAccId] = round(($inventoryByAccount[$invAccId] ?? 0) + $lineTotal, 2);

                $linePayload[] = [
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ];
            }

            $journalLines = [];
            foreach ($inventoryByAccount as $accountId => $amount) {
                $journalLines[] = new JournalLineData(account_id: (int) $accountId, debit: (float) $amount, credit: 0);
            }
            $journalLines[] = new JournalLineData(account_id: (int) $payable->id, debit: 0, credit: $invoiceTotal);

            // Always create as draft; posting is tied to stock update.
            $journalEntry = $this->journalService->create(
                new JournalData(
                    date: (string) $data['invoice_date'],
                    description: (string) ($data['description'] ?? ('Purchase invoice '.$data['invoice_no'])),
                    accounting_period_id: (int) $period->id,
                    lines: $journalLines,
                    entity_type: 'vendor',
                    entity_id: $vendorId > 0 ? $vendorId : null,
                ),
                reason: 'Purchase invoice',
                autoPostOverride: false,
            );

            /** @var PurchaseInvoice $invoice */
            $invoice = PurchaseInvoice::query()->create([
                'invoice_no' => (string) $data['invoice_no'],
                'invoice_date' => (string) $data['invoice_date'],
                'vendor_id' => $data['vendor_id'] ?? null,
                'description' => $data['description'] ?? null,
                'amount' => $invoiceTotal,
                'paid_amount' => 0,
                'status' => 'unpaid',
                'posted_at' => null,
                'journal_entry_id' => (int) $journalEntry->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($linePayload as $lp) {
                $lp['purchase_invoice_id'] = $invoice->id;
                PurchaseInvoiceLine::query()->create($lp);
            }

            if (AppSetting::getBool('journals.auto_post', false)) {
                $this->postInternal($invoice->id, $user, asSystem: true);
            }

            return $invoice->fresh(['purchaseInvoiceLines.item', 'purchaseInvoiceLines.warehouse', 'journalEntry.journalLines.account']);
        });
    }

    public function post(int $invoiceId): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoiceId): PurchaseInvoice {
            $user = $this->resolveUserOrFail();
            $this->postInternal($invoiceId, $user, asSystem: false);

            /** @var PurchaseInvoice $invoice */
            $invoice = PurchaseInvoice::query()->findOrFail($invoiceId);

            return $invoice->fresh(['purchaseInvoiceLines.item', 'purchaseInvoiceLines.warehouse', 'journalEntry.journalLines.account']);
        });
    }

    /**
     * Update an unposted purchase invoice (draft journal, no payments).
     *
     * @param array{
     *   invoice_no:string,
     *   invoice_date:string,
     *   vendor_id?:int|null,
     *   description?:string|null,
     *   lines:list<array{item_id:int,warehouse_id:int,quantity:float|int,unit_cost:float|int}>
     * } $data
     */
    public function update(int $invoiceId, array $data): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoiceId, $data): PurchaseInvoice {
            $user = $this->resolveUserOrFail();

            /** @var PurchaseInvoice $invoice */
            $invoice = PurchaseInvoice::query()
                ->with(['purchaseInvoiceLines', 'purchasePayments', 'journalEntry'])
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            $wasPosted = $invoice->posted_at !== null;

            if ($wasPosted) {
                $this->ensureDangerousEditDeleteAllowedOrFail($user);

                if ($this->hasPostedDependencies($invoice)) {
                    throw ValidationException::withMessages([
                        'dependency' => ['Cannot edit/delete posted purchase invoice because it has payments/returns.'],
                    ]);
                }

                // Dangerous: reverse and remove prior stock movements, void old journal, then re-apply as "posted".
                $this->stockReversalService->reverseReference(
                    referenceType: 'purchase_invoice',
                    referenceId: (int) $invoice->id,
                    movementTypes: ['purchase'],
                    deleteOriginalMovements: true,
                    reversalMovementType: null,
                );

                if ($invoice->journal_entry_id) {
                    $this->journalService->void((int) $invoice->journal_entry_id, 'Dangerous update posted purchase invoice');
                }
            } else {
                if ($invoice->paid_amount > 0 || $invoice->purchasePayments->count() > 0) {
                    throw ValidationException::withMessages([
                        'paid_amount' => ['Cannot update a purchase invoice that already has payments.'],
                    ]);
                }

                $journalEntry = $invoice->journalEntry;
                if (! $journalEntry || $journalEntry->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'journal_entry_id' => ['Only draft purchase invoices can be updated.'],
                    ]);
                }
            }

            $journalEntry = $invoice->journalEntry;

            $newInvoiceNo = (string) $data['invoice_no'];
            if ($newInvoiceNo !== (string) $invoice->invoice_no) {
                if (PurchaseInvoice::query()
                    ->where('invoice_no', $newInvoiceNo)
                    ->where('id', '!=', $invoice->id)
                    ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'invoice_no' => ['Invoice number already exists.'],
                    ]);
                }
            }

            $period = $this->resolvePeriodForDate((string) $data['invoice_date']);
            $payable = $this->resolveAccountByCode((string) config('accounting.auto_journal.accounts.accounts_payable'));

            $lines = $data['lines'] ?? [];
            if (! is_array($lines) || count($lines) < 1) {
                throw ValidationException::withMessages([
                    'lines' => ['At least one invoice line is required.'],
                ]);
            }

            $inventoryByAccount = [];
            $linePayload = [];
            $invoiceTotal = 0.0;

            foreach ($lines as $idx => $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                $warehouseId = (int) ($line['warehouse_id'] ?? 0);
                $qty = (float) ($line['quantity'] ?? 0);
                $unitCost = (float) ($line['unit_cost'] ?? 0);

                if ($itemId <= 0 || $warehouseId <= 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}" => ['item_id and warehouse_id are required.'],
                    ]);
                }
                if ($qty <= 0 || $unitCost <= 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}" => ['quantity and unit_cost must be greater than zero.'],
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
                $unitCost = round($unitCost, 6);
                $lineTotal = round($qty * $unitCost, 2);

                $invoiceTotal = round($invoiceTotal + $lineTotal, 2);

                $invAccId = (int) $item->inventory_account_id;
                $inventoryByAccount[$invAccId] = round(($inventoryByAccount[$invAccId] ?? 0) + $lineTotal, 2);

                $linePayload[] = [
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ];
            }

            $journalLines = [];
            foreach ($inventoryByAccount as $accountId => $amount) {
                $journalLines[] = new JournalLineData(account_id: (int) $accountId, debit: (float) $amount, credit: 0);
            }
            $journalLines[] = new JournalLineData(account_id: (int) $payable->id, debit: 0, credit: $invoiceTotal);

            if ($invoice->posted_at === null) {
                $this->journalService->update(
                    (int) $journalEntry->id,
                    new JournalData(
                        date: (string) $data['invoice_date'],
                        description: (string) ($data['description'] ?? ('Purchase invoice '.$newInvoiceNo)),
                        accounting_period_id: (int) $period->id,
                        lines: $journalLines,
                    ),
                    reason: 'Update purchase invoice',
                );
            } else {
                $newJournalEntry = $this->journalService->create(
                    new JournalData(
                        date: (string) $data['invoice_date'],
                        description: (string) ($data['description'] ?? ('Purchase invoice '.$newInvoiceNo)),
                        accounting_period_id: (int) $period->id,
                        lines: $journalLines,
                    ),
                    reason: 'Dangerous update purchase invoice (repost)',
                    autoPostOverride: false,
                );

                $invoice->forceFill([
                    'journal_entry_id' => (int) $newJournalEntry->id,
                ]);
            }

            $invoice->forceFill([
                'invoice_no' => $newInvoiceNo,
                'invoice_date' => (string) $data['invoice_date'],
                'vendor_id' => $data['vendor_id'] ?? $invoice->vendor_id,
                'description' => $data['description'] ?? null,
                'amount' => $invoiceTotal,
                'paid_amount' => 0,
                'status' => 'unpaid',
                'posted_at' => $wasPosted ? null : $invoice->posted_at,
                'updated_by' => $user->id,
            ])->save();

            PurchaseInvoiceLine::query()->where('purchase_invoice_id', $invoice->id)->delete();
            foreach ($linePayload as $lp) {
                $lp['purchase_invoice_id'] = $invoice->id;
                PurchaseInvoiceLine::query()->create($lp);
            }

            if ($wasPosted) {
                // Re-post to re-apply stock + post the new journal.
                $this->postInternal((int) $invoice->id, $user, asSystem: false);
            }

            return $invoice->fresh(['purchaseInvoiceLines.item', 'purchaseInvoiceLines.warehouse', 'journalEntry.journalLines.account']);
        });
    }

    public function delete(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId): void {
            $user = $this->resolveUserOrFail();

            /** @var PurchaseInvoice $invoice */
            $invoice = PurchaseInvoice::query()
                ->with(['purchasePayments', 'journalEntry'])
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            if ($invoice->posted_at !== null) {
                $this->ensureDangerousEditDeleteAllowedOrFail($user);

                if ($this->hasPostedDependencies($invoice)) {
                    throw ValidationException::withMessages([
                        'dependency' => ['Cannot edit/delete posted purchase invoice because it has payments/returns.'],
                    ]);
                }

                $this->stockReversalService->reverseReference(
                    referenceType: 'purchase_invoice',
                    referenceId: (int) $invoice->id,
                    movementTypes: ['purchase'],
                    deleteOriginalMovements: true,
                    reversalMovementType: null,
                );
            }

            if ($invoice->paid_amount > 0 || $invoice->purchasePayments->count() > 0) {
                throw ValidationException::withMessages([
                    'paid_amount' => ['Cannot delete a purchase invoice that already has payments.'],
                ]);
            }

            $journalEntry = $invoice->journalEntry;
            if ($invoice->posted_at === null) {
                if (! $journalEntry || $journalEntry->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'journal_entry_id' => ['Only draft purchase invoices can be deleted.'],
                    ]);
                }
            }

            if ($journalEntry) {
                $this->journalService->void((int) $journalEntry->id, $invoice->posted_at !== null ? 'Dangerous delete posted purchase invoice' : 'Delete purchase invoice');
            }

            $invoice->forceFill([
                'updated_by' => $user->id,
            ])->save();

            $invoice->delete();
        });
    }

    public function void(int $invoiceId, ?string $reason = null): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoiceId, $reason): PurchaseInvoice {
            $user = $this->resolveUserOrFail();

            /** @var PurchaseInvoice $invoice */
            $invoice = PurchaseInvoice::query()
                ->with(['purchaseInvoiceLines', 'purchasePayments', 'journalEntry'])
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            if ($invoice->voided_at !== null) {
                throw ValidationException::withMessages([
                    'voided_at' => ['Purchase invoice is already voided.'],
                ]);
            }

            if ($this->hasPostedDependencies($invoice)) {
                throw ValidationException::withMessages([
                    'dependency' => ['Cannot void posted purchase invoice because it has payments/returns.'],
                ]);
            }

            if ($invoice->posted_at !== null) {
                $this->stockReversalService->reverseReference(
                    referenceType: 'purchase_invoice',
                    referenceId: (int) $invoice->id,
                    movementTypes: ['purchase'],
                    deleteOriginalMovements: false,
                    reversalMovementType: 'void_purchase_invoice',
                );
            }

            if ($invoice->journal_entry_id) {
                $this->journalService->void((int) $invoice->journal_entry_id, $reason ?: 'Void purchase invoice');
            }

            $invoice->forceFill([
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => $user->id,
                'updated_by' => $user->id,
            ])->save();

            return $invoice->fresh(['purchaseInvoiceLines.item', 'purchaseInvoiceLines.warehouse', 'journalEntry.journalLines.account']);
        });
    }

    private function postInternal(int $invoiceId, User $user, bool $asSystem): void
    {
        /** @var PurchaseInvoice $invoice */
        $invoice = PurchaseInvoice::query()
            ->with(['purchaseInvoiceLines', 'journalEntry.accountingPeriod'])
            ->lockForUpdate()
            ->findOrFail($invoiceId);

        if ($invoice->posted_at !== null) {
            throw ValidationException::withMessages([
                'posted_at' => ['Purchase invoice is already posted.'],
            ]);
        }

        $journalEntry = $invoice->journalEntry;
        if (! $journalEntry || $journalEntry->status !== 'draft') {
            throw ValidationException::withMessages([
                'journal_entry_id' => ['Purchase invoice journal must be draft before posting.'],
            ]);
        }

        if ($invoice->purchaseInvoiceLines->count() < 1) {
            throw ValidationException::withMessages([
                'lines' => ['This purchase invoice has no lines.'],
            ]);
        }

        foreach ($invoice->purchaseInvoiceLines as $line) {
            $itemId = (int) $line->item_id;
            $warehouseId = (int) $line->warehouse_id;
            $qtyIn = round((float) $line->quantity, 4);
            $unitCost = round((float) $line->unit_cost, 6);

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
            $oldAvg = round((float) $balance->avg_cost, 6);
            $oldValue = round($oldQty * $oldAvg, 2);

            $totalCost = round($qtyIn * $unitCost, 2);
            $newQty = round($oldQty + $qtyIn, 4);
            $newAvg = $newQty > 0 ? round(($oldValue + $totalCost) / $newQty, 6) : 0.0;

            $balance->forceFill([
                'quantity' => $newQty,
                'avg_cost' => $newAvg,
            ])->save();

            $this->stockMovementService->record(
                date: (string) $invoice->invoice_date?->format('Y-m-d'),
                itemId: $itemId,
                warehouseId: $warehouseId,
                type: 'purchase',
                qtyIn: $qtyIn,
                qtyOut: 0,
                unitCost: $unitCost,
                referenceType: 'purchase_invoice',
                referenceId: (int) $invoice->id,
            );
        }

        if ($asSystem) {
            $this->journalService->postAsSystem($journalEntry, $user, 'Auto-post purchase invoice');
        } else {
            $this->journalService->post((int) $journalEntry->id, 'Post purchase invoice');
        }

        $invoice->forceFill([
            'posted_at' => now(),
            'updated_by' => $user->id,
        ])->save();
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

    private function hasPostedDependencies(PurchaseInvoice $invoice): bool
    {
        if ((float) $invoice->paid_amount > 0 || $invoice->purchasePayments()->count() > 0) {
            return true;
        }

        return PurchaseReturn::query()->where('purchase_invoice_id', (int) $invoice->id)->exists();
    }
}