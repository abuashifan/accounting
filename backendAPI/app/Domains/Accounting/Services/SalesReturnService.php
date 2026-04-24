<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Actions\CheckPeriodAction;
use App\Domains\Accounting\Actions\CreateJournalAction;
use App\Domains\Accounting\Actions\ValidateJournalAction;
use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Models\AccountingPeriod;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesReturn;
use App\Models\SalesReturnLine;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SalesReturnService
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

    /**
     * Create a sales return (retur penjualan) for a posted sales invoice.
     * It creates a draft journal: Revenue (D) vs AR (C). Stock is updated at posting time.
     *
     * @param array{
     *   return_no:string,
     *   return_date:string,
     *   invoice_id:int,
     *   customer_id?:int|null,
     *   description?:string|null,
     *   lines:list<array{item_id:int,warehouse_id:int,quantity:float|int,unit_price:float|int}>
     * } $data
     */
    public function create(array $data): SalesReturn
    {
        return DB::transaction(function () use ($data): SalesReturn {
            $this->ensureReturnNumberIsUnique((string) $data['return_no']);

            /** @var Invoice $invoice */
            $invoice = Invoice::query()->findOrFail((int) $data['invoice_id']);
            if ($invoice->posted_at === null) {
                throw ValidationException::withMessages([
                    'invoice_id' => ['Invoice must be posted before creating a sales return.'],
                ]);
            }

            $lines = $data['lines'] ?? [];
            if (! is_array($lines) || count($lines) < 1) {
                throw ValidationException::withMessages([
                    'lines' => ['At least one return line is required.'],
                ]);
            }

            $customerId = (int) ($data['customer_id'] ?? 0);
            if ($customerId > 0) {
                Customer::query()->findOrFail($customerId);
            }

            $user = $this->resolveUserOrFail();
            $period = $this->resolvePeriodForDate((string) $data['return_date']);
            $receivableAccount = $this->resolveAccountByCode(
                (string) config('accounting.auto_journal.accounts.accounts_receivable')
            );

            $revenueDebitByAccount = [];
            $linePayload = [];
            $total = 0.0;

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

                $total = round($total + $lineTotal, 2);

                $revAccId = (int) $item->revenue_account_id;
                $revenueDebitByAccount[$revAccId] = round(($revenueDebitByAccount[$revAccId] ?? 0) + $lineTotal, 2);

                $linePayload[] = [
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Amount must be greater than zero.'],
                ]);
            }

            $journalLines = [];
            foreach ($revenueDebitByAccount as $accountId => $amt) {
                $journalLines[] = new JournalLineData(account_id: (int) $accountId, debit: (float) $amt, credit: 0);
            }
            $journalLines[] = new JournalLineData(account_id: (int) $receivableAccount->id, debit: 0, credit: $total);

            // Force draft; stock + COGS reversal is applied at posting time.
            $journalEntry = $this->journalService->create(
                new JournalData(
                    date: (string) $data['return_date'],
                    description: (string) ($data['description'] ?? ('Sales return '.$data['return_no'])),
                    accounting_period_id: (int) $period->id,
                    lines: $journalLines,
                    entity_type: 'customer',
                    entity_id: $customerId > 0 ? $customerId : null,
                ),
                reason: 'Sales return',
                autoPostOverride: false,
            );

            /** @var SalesReturn $return */
            $return = SalesReturn::query()->create([
                'return_no' => (string) $data['return_no'],
                'return_date' => (string) $data['return_date'],
                'invoice_id' => (int) $data['invoice_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'description' => $data['description'] ?? null,
                'amount' => $total,
                'posted_at' => null,
                'journal_entry_id' => (int) $journalEntry->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($linePayload as $lp) {
                $lp['sales_return_id'] = $return->id;
                SalesReturnLine::query()->create($lp);
            }

            $autoPost = AppSetting::getBool('journals.auto_post', false);
            if ($autoPost) {
                $this->postInternal($return->id, $user, asSystem: true);
            }

            return $return->fresh(['salesReturnLines.item', 'salesReturnLines.warehouse', 'journalEntry.journalLines.account', 'invoice']);
        });
    }

    public function post(int $salesReturnId): SalesReturn
    {
        return DB::transaction(function () use ($salesReturnId): SalesReturn {
            $user = $this->resolveUserOrFail();
            $this->postInternal($salesReturnId, $user, asSystem: false);

            /** @var SalesReturn $return */
            $return = SalesReturn::query()->findOrFail($salesReturnId);

            return $return->fresh(['salesReturnLines.item', 'salesReturnLines.warehouse', 'journalEntry.journalLines.account', 'invoice']);
        });
    }

    /**
     * @param array{
     *   return_no:string,
     *   return_date:string,
     *   customer_id?:int|null,
     *   description?:string|null,
     *   lines:list<array{item_id:int,warehouse_id:int,quantity:float|int,unit_price:float|int}>
     * } $data
     */
    public function update(int $salesReturnId, array $data): SalesReturn
    {
        return DB::transaction(function () use ($salesReturnId, $data): SalesReturn {
            $user = $this->resolveUserOrFail();

            /** @var SalesReturn $return */
            $return = SalesReturn::query()
                ->with(['salesReturnLines', 'journalEntry', 'invoice'])
                ->lockForUpdate()
                ->findOrFail($salesReturnId);

            if ($return->voided_at !== null) {
                throw ValidationException::withMessages([
                    'voided_at' => ['Voided sales returns cannot be updated.'],
                ]);
            }

            $wasPosted = $return->posted_at !== null;

            if ($wasPosted) {
                $this->ensureDangerousEditDeleteAllowedOrFail($user);

                $this->stockReversalService->reverseReference(
                    referenceType: 'sales_return',
                    referenceId: (int) $return->id,
                    movementTypes: ['sales_return'],
                    deleteOriginalMovements: true,
                    reversalMovementType: null,
                );

                if ($return->journal_entry_id) {
                    $this->journalService->void((int) $return->journal_entry_id, 'Dangerous update posted sales return');
                }
            } else {
                $journalEntry = $return->journalEntry;
                if (! $journalEntry || $journalEntry->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'journal_entry_id' => ['Only draft sales returns can be updated.'],
                    ]);
                }
            }

            $journalEntry = $return->journalEntry;

            $newNo = (string) $data['return_no'];
            if ($newNo !== (string) $return->return_no) {
                if (SalesReturn::query()
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
            $receivableAccount = $this->resolveAccountByCode(
                (string) config('accounting.auto_journal.accounts.accounts_receivable')
            );

            $lines = $data['lines'] ?? [];
            if (! is_array($lines) || count($lines) < 1) {
                throw ValidationException::withMessages([
                    'lines' => ['At least one return line is required.'],
                ]);
            }

            $revenueDebitByAccount = [];
            $linePayload = [];
            $total = 0.0;

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

                $total = round($total + $lineTotal, 2);

                $revAccId = (int) $item->revenue_account_id;
                $revenueDebitByAccount[$revAccId] = round(($revenueDebitByAccount[$revAccId] ?? 0) + $lineTotal, 2);

                $linePayload[] = [
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $journalLines = [];
            foreach ($revenueDebitByAccount as $accountId => $amt) {
                $journalLines[] = new JournalLineData(account_id: (int) $accountId, debit: (float) $amt, credit: 0);
            }
            $journalLines[] = new JournalLineData(account_id: (int) $receivableAccount->id, debit: 0, credit: $total);

            $dataJournal = new JournalData(
                date: (string) $data['return_date'],
                description: (string) (($data['description'] ?? null) ?: ('Sales return '.$newNo)),
                accounting_period_id: (int) $period->id,
                lines: $journalLines,
            );

            if (! $wasPosted) {
                $this->journalService->update((int) $journalEntry->id, $dataJournal, 'Update sales return');
            } else {
                $newJournal = $this->journalService->create($dataJournal, reason: 'Dangerous update sales return (repost)', autoPostOverride: false);
                $return->forceFill([
                    'journal_entry_id' => (int) $newJournal->id,
                ]);
            }

            $return->forceFill([
                'return_no' => $newNo,
                'return_date' => (string) $data['return_date'],
                'customer_id' => $data['customer_id'] ?? $return->customer_id,
                'description' => $data['description'] ?? null,
                'amount' => $total,
                'posted_at' => $wasPosted ? null : $return->posted_at,
                'updated_by' => $user->id,
            ])->save();

            SalesReturnLine::query()->where('sales_return_id', $return->id)->delete();
            foreach ($linePayload as $lp) {
                $lp['sales_return_id'] = $return->id;
                SalesReturnLine::query()->create($lp);
            }

            if ($wasPosted) {
                $this->postInternal((int) $return->id, $user, asSystem: false);
            }

            return $return->fresh(['salesReturnLines.item', 'salesReturnLines.warehouse', 'journalEntry.journalLines.account', 'invoice']);
        });
    }

    public function delete(int $salesReturnId): void
    {
        DB::transaction(function () use ($salesReturnId): void {
            $user = $this->resolveUserOrFail();

            /** @var SalesReturn $return */
            $return = SalesReturn::query()
                ->with(['journalEntry'])
                ->lockForUpdate()
                ->findOrFail($salesReturnId);

            if ($return->voided_at !== null) {
                throw ValidationException::withMessages([
                    'voided_at' => ['Voided sales returns cannot be deleted.'],
                ]);
            }

            if ($return->posted_at !== null) {
                $this->ensureDangerousEditDeleteAllowedOrFail($user);

                $this->stockReversalService->reverseReference(
                    referenceType: 'sales_return',
                    referenceId: (int) $return->id,
                    movementTypes: ['sales_return'],
                    deleteOriginalMovements: true,
                    reversalMovementType: null,
                );
            }

            $journalEntry = $return->journalEntry;
            if ($journalEntry) {
                $this->journalService->void((int) $journalEntry->id, $return->posted_at !== null ? 'Dangerous delete posted sales return' : 'Delete sales return');
            }

            $return->delete();
        });
    }

    public function void(int $salesReturnId, ?string $reason = null): SalesReturn
    {
        return DB::transaction(function () use ($salesReturnId, $reason): SalesReturn {
            $user = $this->resolveUserOrFail();

            /** @var SalesReturn $return */
            $return = SalesReturn::query()
                ->with(['salesReturnLines', 'journalEntry', 'invoice'])
                ->lockForUpdate()
                ->findOrFail($salesReturnId);

            if ($return->voided_at !== null) {
                throw ValidationException::withMessages([
                    'voided_at' => ['Sales return is already voided.'],
                ]);
            }

            if ($return->posted_at !== null) {
                $this->stockReversalService->reverseReference(
                    referenceType: 'sales_return',
                    referenceId: (int) $return->id,
                    movementTypes: ['sales_return'],
                    deleteOriginalMovements: false,
                    reversalMovementType: 'void_sales_return',
                );
            }

            if ($return->journal_entry_id) {
                $this->journalService->void((int) $return->journal_entry_id, $reason ?: 'Void sales return');
            }

            $return->forceFill([
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => $user->id,
                'updated_by' => $user->id,
            ])->save();

            return $return->fresh(['salesReturnLines.item', 'salesReturnLines.warehouse', 'journalEntry.journalLines.account', 'invoice']);
        });
    }

    private function postInternal(int $salesReturnId, User $user, bool $asSystem): void
    {
        /** @var SalesReturn $return */
        $return = SalesReturn::query()
            ->with(['salesReturnLines', 'journalEntry.accountingPeriod', 'journalEntry.journalLines', 'invoice'])
            ->lockForUpdate()
            ->findOrFail($salesReturnId);

        if ($return->posted_at !== null) {
            throw ValidationException::withMessages([
                'posted_at' => ['Sales return is already posted.'],
            ]);
        }

        if ($return->salesReturnLines->count() < 1) {
            throw ValidationException::withMessages([
                'lines' => ['This sales return has no lines.'],
            ]);
        }

        $journalEntry = $return->journalEntry;
        if (! $journalEntry || $journalEntry->status !== 'draft') {
            throw ValidationException::withMessages([
                'journal_entry_id' => ['Sales return journal must be draft before posting.'],
            ]);
        }

        /** @var Invoice $invoice */
        $invoice = $return->invoice;
        if (! $invoice || $invoice->posted_at === null) {
            throw ValidationException::withMessages([
                'invoice_id' => ['Invoice must be posted before posting a sales return.'],
            ]);
        }

        $cogsCreditByAccount = [];
        $invDebitByAccount = [];

        foreach ($return->salesReturnLines as $line) {
            $itemId = (int) $line->item_id;
            $warehouseId = (int) $line->warehouse_id;
            $qtyIn = round((float) $line->quantity, 4);

            /** @var Item $item */
            $item = Item::query()->findOrFail($itemId);
            Warehouse::query()->findOrFail($warehouseId);

            /** @var StockMovement|null $saleMv */
            $saleMv = StockMovement::query()
                ->where('type', 'sale')
                ->where('reference_type', 'invoice')
                ->where('reference_id', (int) $invoice->id)
                ->where('item_id', $itemId)
                ->where('warehouse_id', $warehouseId)
                ->orderByDesc('id')
                ->first();

            $unitCost = $saleMv ? round((float) $saleMv->unit_cost, 6) : null;

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

            $unitCost = $unitCost ?? $oldAvg;
            $oldValue = round($oldQty * $oldAvg, 2);
            $addValue = round($qtyIn * $unitCost, 2);
            $newQty = round($oldQty + $qtyIn, 4);
            $newAvg = $newQty > 0 ? round(($oldValue + $addValue) / $newQty, 6) : 0.0;

            $balance->forceFill([
                'quantity' => $newQty,
                'avg_cost' => $newAvg,
            ])->save();

            $this->stockMovementService->record(
                date: (string) $return->return_date?->format('Y-m-d'),
                itemId: $itemId,
                warehouseId: $warehouseId,
                type: 'sales_return',
                qtyIn: $qtyIn,
                qtyOut: 0,
                unitCost: $unitCost,
                referenceType: 'sales_return',
                referenceId: (int) $return->id,
            );

            $cogs = round($qtyIn * $unitCost, 2);
            $cogsAccId = (int) $item->cogs_account_id;
            $invAccId = (int) $item->inventory_account_id;

            $cogsCreditByAccount[$cogsAccId] = round(($cogsCreditByAccount[$cogsAccId] ?? 0) + $cogs, 2);
            $invDebitByAccount[$invAccId] = round(($invDebitByAccount[$invAccId] ?? 0) + $cogs, 2);
        }

        // Build full journal: Revenue debits + AR credit already implied by return lines, add Inventory debit + COGS credit.
        $return->loadMissing(['salesReturnLines.item', 'journalEntry.accountingPeriod']);

        $revenueDebitByAccount = [];
        foreach ($return->salesReturnLines as $line) {
            $item = $line->item;
            if (! $item) {
                continue;
            }
            $revAccId = (int) $item->revenue_account_id;
            $revenueDebitByAccount[$revAccId] = round(($revenueDebitByAccount[$revAccId] ?? 0) + (float) $line->line_total, 2);
        }

        $fullLines = [];
        foreach ($revenueDebitByAccount as $accountId => $amt) {
            $fullLines[] = new JournalLineData(account_id: (int) $accountId, debit: (float) $amt, credit: 0);
        }

        $fullLines[] = new JournalLineData(
            account_id: $this->resolveAccountByCode((string) config('accounting.auto_journal.accounts.accounts_receivable'))->id,
            debit: 0,
            credit: (float) $return->amount,
        );

        foreach ($invDebitByAccount as $accountId => $amt) {
            $fullLines[] = new JournalLineData(account_id: (int) $accountId, debit: (float) $amt, credit: 0);
        }
        foreach ($cogsCreditByAccount as $accountId => $amt) {
            $fullLines[] = new JournalLineData(account_id: (int) $accountId, debit: 0, credit: (float) $amt);
        }

        $data = new JournalData(
            date: (string) $return->return_date?->format('Y-m-d'),
            description: (string) ($return->description ?? ('Sales return '.$return->return_no)),
            accounting_period_id: (int) $journalEntry->accounting_period_id,
            lines: $fullLines,
        );

        if ($asSystem) {
            $this->validateJournalAction->execute($data);
            $this->checkPeriodAction->execute($journalEntry->accountingPeriod, $user);
            $updatedJournal = $this->createJournalAction->execute($data, $user, $journalEntry);
            $this->journalService->postAsSystem($updatedJournal, $user, 'Auto-post sales return');
        } else {
            $this->journalService->update((int) $journalEntry->id, $data, 'Post sales return');
            $this->journalService->post((int) $journalEntry->id, 'Post sales return');
        }

        $return->forceFill([
            'posted_at' => now(),
            'updated_by' => $user->id,
        ])->save();
    }

    private function ensureReturnNumberIsUnique(string $returnNo): void
    {
        if (SalesReturn::query()->where('return_no', $returnNo)->exists()) {
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