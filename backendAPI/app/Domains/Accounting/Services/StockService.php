<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\Item;
use App\Domains\Accounting\Models\StockBalance;
use App\Domains\Accounting\Models\Warehouse;
use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    private const KEY_ALLOW_NEGATIVE_STOCK = 'inventory.allow_negative_stock';

    public function __construct(
        private readonly StockMovementService $stockMovementService,
        private readonly JournalService $journalService,
    ) {}

    /**
     * PURCHASE: increase stock and create journal.
     *
     * @return array{stock_balance: StockBalance, journal_entry_id: int, movement_id: int, new_avg_cost: float}
     */
    public function purchase(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $item = Item::query()->findOrFail((int) $data['item_id']);
            Warehouse::query()->findOrFail((int) $data['warehouse_id']);

            if (($item->cost_method ?? 'average') !== 'average') {
                throw ValidationException::withMessages([
                    'cost_method' => ['Only average cost is supported.'],
                ]);
            }

            $qtyIn = $this->assertPositive((float) $data['quantity'], 'quantity');
            $unitCost = $this->assertPositive((float) $data['unit_cost'], 'unit_cost');
            $totalCost = round($qtyIn * $unitCost, 2);

            $balance = $this->lockOrCreateBalance($item->id, (int) $data['warehouse_id']);

            $oldQty = round((float) $balance->quantity, 4);
            $oldAvg = round((float) $balance->avg_cost, 6);
            $oldValue = round($oldQty * $oldAvg, 2);

            $newQty = round($oldQty + $qtyIn, 4);
            $newAvg = $newQty > 0
                ? round(($oldValue + $totalCost) / $newQty, 6)
                : 0.0;

            $balance->forceFill([
                'quantity' => $newQty,
                'avg_cost' => $newAvg,
            ])->save();

            $movement = $this->stockMovementService->record(
                date: (string) $data['date'],
                itemId: $item->id,
                warehouseId: (int) $data['warehouse_id'],
                type: 'purchase',
                qtyIn: $qtyIn,
                qtyOut: 0,
                unitCost: $unitCost,
                referenceType: $data['reference_type'] ?? null,
                referenceId: isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            )['movement'];

            $journal = $this->journalService->create(
                new JournalData(
                    date: (string) $data['date'],
                    description: (string) ($data['description'] ?? 'Inventory purchase'),
                    accounting_period_id: (int) $data['accounting_period_id'],
                    lines: [
                        new JournalLineData(account_id: (int) $item->inventory_account_id, debit: $totalCost, credit: 0),
                        new JournalLineData(account_id: (int) $data['credit_account_id'], debit: 0, credit: $totalCost),
                    ],
                ),
                reason: 'Inventory purchase',
            );

            return [
                'stock_balance' => $balance->fresh(),
                'journal_entry_id' => (int) $journal->id,
                'movement_id' => (int) $movement->id,
                'new_avg_cost' => (float) $newAvg,
            ];
        });
    }

    /**
     * SALE: decrease stock and create revenue + COGS journal.
     *
     * @return array{stock_balance: StockBalance, journal_entry_id: int, movement_id: int, cogs_total: float, revenue_total: float}
     */
    public function sale(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $item = Item::query()->findOrFail((int) $data['item_id']);
            Warehouse::query()->findOrFail((int) $data['warehouse_id']);

            if (($item->cost_method ?? 'average') !== 'average') {
                throw ValidationException::withMessages([
                    'cost_method' => ['Only average cost is supported.'],
                ]);
            }

            $qtyOut = $this->assertPositive((float) $data['quantity'], 'quantity');
            $unitPrice = isset($data['unit_price'])
                ? $this->assertPositive((float) $data['unit_price'], 'unit_price')
                : round((float) $item->selling_price, 2);

            $balance = $this->lockOrCreateBalance($item->id, (int) $data['warehouse_id']);

            $oldQty = round((float) $balance->quantity, 4);
            $avgCost = round((float) $balance->avg_cost, 6);

            $this->assertNotNegative($oldQty - $qtyOut, 'quantity');

            $cogsTotal = round($qtyOut * $avgCost, 2);
            $revenueTotal = round($qtyOut * $unitPrice, 2);

            $newQty = round($oldQty - $qtyOut, 4);
            $balance->forceFill([
                'quantity' => $newQty,
                'avg_cost' => $avgCost,
            ])->save();

            $movement = $this->stockMovementService->record(
                date: (string) $data['date'],
                itemId: $item->id,
                warehouseId: (int) $data['warehouse_id'],
                type: 'sale',
                qtyIn: 0,
                qtyOut: $qtyOut,
                unitCost: $avgCost,
                referenceType: $data['reference_type'] ?? null,
                referenceId: isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            )['movement'];

            $journal = $this->journalService->create(
                new JournalData(
                    date: (string) $data['date'],
                    description: (string) ($data['description'] ?? 'Inventory sale'),
                    accounting_period_id: (int) $data['accounting_period_id'],
                    lines: [
                        // Revenue
                        new JournalLineData(account_id: (int) $data['debit_account_id'], debit: $revenueTotal, credit: 0),
                        new JournalLineData(account_id: (int) $item->revenue_account_id, debit: 0, credit: $revenueTotal),
                        // COGS
                        new JournalLineData(account_id: (int) $item->cogs_account_id, debit: $cogsTotal, credit: 0),
                        new JournalLineData(account_id: (int) $item->inventory_account_id, debit: 0, credit: $cogsTotal),
                    ],
                ),
                reason: 'Inventory sale',
            );

            return [
                'stock_balance' => $balance->fresh(),
                'journal_entry_id' => (int) $journal->id,
                'movement_id' => (int) $movement->id,
                'cogs_total' => (float) $cogsTotal,
                'revenue_total' => (float) $revenueTotal,
            ];
        });
    }

    private function lockOrCreateBalance(int $itemId, int $warehouseId): StockBalance
    {
        /** @var StockBalance|null $balance */
        $balance = StockBalance::query()
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($balance instanceof StockBalance) {
            return $balance;
        }

        StockBalance::query()->create([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'avg_cost' => 0,
        ]);

        /** @var StockBalance $locked */
        $locked = StockBalance::query()
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    private function assertPositive(float $value, string $field): float
    {
        if ($value <= 0) {
            throw ValidationException::withMessages([
                $field => ['Must be greater than zero.'],
            ]);
        }

        return $value;
    }

    private function assertNotNegative(float $newQty, string $field): void
    {
        $allowNegative = AppSetting::getBool(self::KEY_ALLOW_NEGATIVE_STOCK, false);

        if (! $allowNegative && round($newQty, 4) < 0) {
            throw ValidationException::withMessages([
                $field => ['Stock cannot be negative.'],
            ]);
        }
    }
}

