<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Models\AccountingPeriod;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnLine;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
{
    private const KEY_ALLOW_ADMIN_EDIT_DELETE_POSTED = 'transactions.allow_admin_edit_delete_posted';

    public function __construct(
        private readonly JournalService $journalService,
        private readonly StockMovementService $stockMovementService,
        private readonly StockReversalService $stockReversalService,
    ) {}

    /**
     * Create a purchase return (retur pembelian) for a posted purchase invoice.
     * It creates a draft journal: AP (D) vs Inventory (C). Stock is updated at posting time.
     *
     * @param array{
     *   return_no:string,
     *   return_date:string,
     *   purchase_invoice_id:int,
     *   vendor_id?:int|null,
     *   description?:string|null,
     *   lines:list<array{item_id:int,warehouse_id:int,quantity:float|int,unit_cost:float|int}>
     * } $data
     */
    public function create(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data): PurchaseReturn {
            $this->ensureReturnNumberIsUnique((string) $data['return_no']);

            /** @var PurchaseInvoice $invoice */
            $invoice = PurchaseInvoice::query()->findOrFail((int) $data['purchase_invoice_id']);
            if ($invoice->posted_at === null) {
                throw ValidationException::withMessages([
                    'purchase_invoice_id' => ['Purchase invoice must be posted before creating a purchase return.'],
                ]);
            }

            $lines = $data['lines'] ?? [];
            if (! is_array($lines) || count($lines) < 1) {
                throw ValidationException::withMessages([
                    'lines' => ['At least one return line is required.'],
                ]);
            }

            $vendorId = (int) ($data['vendor_id'] ?? 0);
            if ($vendorId > 0) {
                Vendor::query()->findOrFail($vendorId);
            }

            $user = $this->resolveUserOrFail();
            $period = $this->resolvePeriodForDate((string) $data['return_date']);
            $payable = $this->resolveAccountByCode((string) config('accounting.auto_journal.accounts.accounts_payable'));

            $inventoryCreditByAccount = [];
            $linePayload = [];
            $total = 0.0;

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
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}.quantity" => ['Must be greater than zero.'],
                    ]);
                }
                if ($unitCost <= 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}.unit_cost" => ['Must be greater than zero.'],
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

                $total = round($total + $lineTotal, 2);

                $invAccId = (int) $item->inventory_account_id;
                $inventoryCreditByAccount[$invAccId] = round(($inventoryCreditByAccount[$invAccId] ?? 0) + $lineTotal, 2);

                $linePayload[] = [
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ];
            }

            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Amount must be greater than zero.'],
                ]);
            }

            $journalLines = [
                new JournalLineData(account_id: (int) $payable->id, debit: $total, credit: 0),
            ];
            foreach ($inventoryCreditByAccount as $accountId => $amt) {
                $journalLines[] = new JournalLineData(account_id: (int) $accountId, debit: 0, credit: (float) $amt);
            }

            $journalEntry = $this->journalService->create(
                new JournalData(
                    date: (string) $data['return_date'],
                    description: (string) ($data['description'] ?? ('Purchase return '.$data['return_no'])),
                    accounting_period_id: (int) $period->id,
                    lines: $journalLines,
                    entity_type: 'vendor',
                    entity_id: $vendorId > 0 ? $vendorId : null,
                ),
                reason: 'Purchase return',
                autoPostOverride: false,
            );

            /** @var PurchaseReturn $return */
            $return = PurchaseReturn::query()->create([
                'return_no' => (string) $data['return_no'],
                'return_date' => (string) $data['return_date'],
                'purchase_invoice_id' => (int) $data['purchase_invoice_id'],
                'vendor_id' => $data['vendor_id'] ?? null,
                'description' => $data['description'] ?? null,
                'amount' => $total,
                'posted_at' => null,
                'journal_entry_id' => (int) $journalEntry->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($linePayload as $lp) {
                $lp['purchase_return_id'] = $return->id;
                PurchaseReturnLine::query()->create($lp);
            }

            $autoPost = AppSetting::getBool('journals.auto_post', false);
            if ($autoPost) {
                $this->postInternal($return->id, $user, asSystem: true);
            }

            return $return->fresh(['purchaseReturnLines.item', 'purchaseReturnLines.warehouse', 'journalEntry.journalLines.account', 'purchaseInvoice']);
        });
    }

    public function post(int $purchaseReturnId): PurchaseReturn
    {
        return DB::transaction(function () use ($purchaseReturnId): PurchaseReturn {
            $user = $this->resolveUserOrFail();
            $this->postInternal($purchaseReturnId, $user, asSystem: false);

            /** @var PurchaseReturn $return */
            $return = PurchaseReturn::query()->findOrFail($purchaseReturnId);

            return $return->fresh(['purchaseReturnLines.item', 'purchaseReturnLines.warehouse', 'journalEntry.journalLines.account', 'purchaseInvoice']);
        });
    }

    /**
     * @param array{
     *   return_no:string,
     *   return_date:string,
     *   vendor_id?:int|null,
     *   description?:string|null,
     *   lines:list<array{item_id:int,warehouse_id:int,quantity:float|int,unit_cost:float|int}>
     * } $data
     */
    public function update(int $purchaseReturnId, array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($purchaseReturnId, $data): PurchaseReturn {
            $user = $this->resolveUserOrFail();

            /** @var PurchaseReturn $return */
            $return = PurchaseReturn::query()
                ->with(['purchaseReturnLines', 'journalEntry', 'purchaseInvoice'])
                ->lockForUpdate()
                ->findOrFail($purchaseReturnId);

            if ($return->voided_at !== null) {
                throw ValidationException::withMessages([
                    'voided_at' => ['Voided purchase returns cannot be updated.'],
                ]);
            }

            $wasPosted = $return->posted_at !== null;

            if ($wasPosted) {
                $this->ensureDangerousEditDeleteAllowedOrFail($user);

                $this->stockReversalService->reverseReference(
                    referenceType: 'purchase_return',
                    referenceId: (int) $return->id,
                    movementTypes: ['purchase_return'],
                    deleteOriginalMovements: true,
                    reversalMovementType: null,
                );

                if ($return->journal_entry_id) {
                    $this->journalService->void((int) $return->journal_entry_id, 'Dangerous update posted purchase return');
                }
            } else {
                $journalEntry = $return->journalEntry;
                if (! $journalEntry || $journalEntry->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'journal_entry_id' => ['Only draft purchase returns can be updated.'],
                    ]);
                }
            }

            $journalEntry = $return->journalEntry;

            $newNo = (string) $data['return_no'];
            if ($newNo !== (string) $return->return_no) {
                if (PurchaseReturn::query()
                    ->where('return_no', $newNo)
                    ->where('id', '!=', $return->id)
                    ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'return_no' => ['Return number already exists.'],
                    ]);
                }
            }

            $period = $this->resolvePeriodForDate((string) $data['return_date']);
            $payable = $this->resolveAccountByCode((string) config('accounting.auto_journal.accounts.accounts_payable'));

            $lines = $data['lines'] ?? [];
            if (! is_array($lines) || count($lines) < 1) {
                throw ValidationException::withMessages([
                    'lines' => ['At least one return line is required.'],
                ]);
            }

            $inventoryCreditByAccount = [];
            $linePayload = [];
            $total = 0.0;

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
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}.quantity" => ['Must be greater than zero.'],
                    ]);
                }
                if ($unitCost <= 0) {
                    throw ValidationException::withMessages([
                        "lines.{$idx}.unit_cost" => ['Must be greater than zero.'],
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

                $total = round($total + $lineTotal, 2);

                $invAccId = (int) $item->inventory_account_id;
                $inventoryCreditByAccount[$invAccId] = round(($inventoryCreditByAccount[$invAccId] ?? 0) + $lineTotal, 2);

                $linePayload[] = [
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ];
            }

            $journalLines = [
                new JournalLineData(account_id: (int) $payable->id, debit: $total, credit: 0),
            ];
            foreach ($inventoryCreditByAccount as $accountId => $amt) {
                $journalLines[] = new JournalLineData(account_id: (int) $accountId, debit: 0, credit: (float) $amt);
            }

            $dataJournal = new JournalData(
                date: (string) $data['return_date'],
                description: (string) (($data['description'] ?? null) ?: ('Purchase return '.$newNo)),
                accounting_period_id: (int) $period->id,
                lines: $journalLines,
            );

            if (! $wasPosted) {
                $this->journalService->update((int) $journalEntry->id, $dataJournal, 'Update purchase return');
            } else {
                $newJournal = $this->journalService->create($dataJournal, reason: 'Dangerous update purchase return (repost)', autoPostOverride: false);
                $return->forceFill([
                    'journal_entry_id' => (int) $newJournal->id,
                ]);
            }

            $return->forceFill([
                'return_no' => $newNo,
                'return_date' => (string) $data['return_date'],
                'vendor_id' => $data['vendor_id'] ?? $return->vendor_id,
                'description' => $data['description'] ?? null,
                'amount' => $total,
                'posted_at' => $wasPosted ? null : $return->posted_at,
                'updated_by' => $user->id,
            ])->save();

            PurchaseReturnLine::query()->where('purchase_return_id', $return->id)->delete();
            foreach ($linePayload as $lp) {
                $lp['purchase_return_id'] = $return->id;
                PurchaseReturnLine::query()->create($lp);
            }

            if ($wasPosted) {
                $this->postInternal((int) $return->id, $user, asSystem: false);
            }

            return $return->fresh(['purchaseReturnLines.item', 'purchaseReturnLines.warehouse', 'journalEntry.journalLines.account', 'purchaseInvoice']);
        });
    }

    public function delete(int $purchaseReturnId): void
    {
        DB::transaction(function () use ($purchaseReturnId): void {
            $user = $this->resolveUserOrFail();

            /** @var PurchaseReturn $return */
            $return = PurchaseReturn::query()
                ->with(['journalEntry'])
                ->lockForUpdate()
                ->findOrFail($purchaseReturnId);

            if ($return->voided_at !== null) {
                throw ValidationException::withMessages([
                    'voided_at' => ['Voided purchase returns cannot be deleted.'],
                ]);
            }

            if ($return->posted_at !== null) {
                $this->ensureDangerousEditDeleteAllowedOrFail($user);

                $this->stockReversalService->reverseReference(
                    referenceType: 'purchase_return',
                    referenceId: (int) $return->id,
                    movementTypes: ['purchase_return'],
                    deleteOriginalMovements: true,
                    reversalMovementType: null,
                );
            }

            $journalEntry = $return->journalEntry;
            if ($journalEntry) {
                $this->journalService->void((int) $journalEntry->id, $return->posted_at !== null ? 'Dangerous delete posted purchase return' : 'Delete purchase return');
            }

            $return->delete();
        });
    }

    public function void(int $purchaseReturnId, ?string $reason = null): PurchaseReturn
    {
        return DB::transaction(function () use ($purchaseReturnId, $reason): PurchaseReturn {
            $user = $this->resolveUserOrFail();

            /** @var PurchaseReturn $return */
            $return = PurchaseReturn::query()
                ->with(['purchaseReturnLines', 'journalEntry', 'purchaseInvoice'])
                ->lockForUpdate()
                ->findOrFail($purchaseReturnId);

            if ($return->voided_at !== null) {
                throw ValidationException::withMessages([
                    'voided_at' => ['Purchase return is already voided.'],
                ]);
            }

            if ($return->posted_at !== null) {
                $this->stockReversalService->reverseReference(
                    referenceType: 'purchase_return',
                    referenceId: (int) $return->id,
                    movementTypes: ['purchase_return'],
                    deleteOriginalMovements: false,
                    reversalMovementType: 'void_purchase_return',
                );
            }

            if ($return->journal_entry_id) {
                $this->journalService->void((int) $return->journal_entry_id, $reason ?: 'Void purchase return');
            }

            $return->forceFill([
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => $user->id,
                'updated_by' => $user->id,
            ])->save();

            return $return->fresh(['purchaseReturnLines.item', 'purchaseReturnLines.warehouse', 'journalEntry.journalLines.account', 'purchaseInvoice']);
        });
    }

    private function postInternal(int $purchaseReturnId, User $user, bool $asSystem): void
    {
        /** @var PurchaseReturn $return */
        $return = PurchaseReturn::query()
            ->with(['purchaseReturnLines', 'journalEntry.accountingPeriod', 'purchaseInvoice'])
            ->lockForUpdate()
            ->findOrFail($purchaseReturnId);

        if ($return->posted_at !== null) {
            throw ValidationException::withMessages([
                'posted_at' => ['Purchase return is already posted.'],
            ]);
        }

        $journalEntry = $return->journalEntry;
        if (! $journalEntry || $journalEntry->status !== 'draft') {
            throw ValidationException::withMessages([
                'journal_entry_id' => ['Purchase return journal must be draft before posting.'],
            ]);
        }

        /** @var PurchaseInvoice $invoice */
        $invoice = $return->purchaseInvoice;
        if (! $invoice || $invoice->posted_at === null) {
            throw ValidationException::withMessages([
                'purchase_invoice_id' => ['Purchase invoice must be posted before posting a purchase return.'],
            ]);
        }

        foreach ($return->purchaseReturnLines as $line) {
            $itemId = (int) $line->item_id;
            $warehouseId = (int) $line->warehouse_id;
            $qtyOut = round((float) $line->quantity, 4);
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

            $allowNegative = AppSetting::getBool('inventory.allow_negative_stock', false);
            if (! $allowNegative && round($oldQty - $qtyOut, 4) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stock cannot be negative.'],
                ]);
            }

            $oldValue = round($oldQty * $oldAvg, 2);
            $outValue = round($qtyOut * $unitCost, 2);
            $newQty = round($oldQty - $qtyOut, 4);
            $newAvg = $newQty > 0 ? round(($oldValue - $outValue) / $newQty, 6) : 0.0;

            $balance->forceFill([
                'quantity' => $newQty,
                'avg_cost' => $newAvg,
            ])->save();

            $this->stockMovementService->record(
                date: (string) $return->return_date?->format('Y-m-d'),
                itemId: $itemId,
                warehouseId: $warehouseId,
                type: 'purchase_return',
                qtyIn: 0,
                qtyOut: $qtyOut,
                unitCost: $unitCost,
                referenceType: 'purchase_return',
                referenceId: (int) $return->id,
            );
        }

        if ($asSystem) {
            $this->journalService->postAsSystem($journalEntry, $user, 'Auto-post purchase return');
        } else {
            $this->journalService->post((int) $journalEntry->id, 'Post purchase return');
        }

        $return->forceFill([
            'posted_at' => now(),
            'updated_by' => $user->id,
        ])->save();
    }

    private function ensureReturnNumberIsUnique(string $returnNo): void
    {
        if (PurchaseReturn::query()->where('return_no', $returnNo)->exists()) {
            throw ValidationException::withMessages([
                'return_no' => ['Return number already exists.'],
            ]);
        }
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
                'return_date' => ['No accounting period covers the given return date.'],
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
}