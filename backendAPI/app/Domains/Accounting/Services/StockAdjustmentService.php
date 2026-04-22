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

class StockAdjustmentService
{
    private const KEY_ALLOW_NEGATIVE_STOCK = 'inventory.allow_negative_stock';

    public function __construct(
        private readonly StockMovementService $stockMovementService,
        private readonly JournalService $journalService,
    ) {}

    /**
     * quantity_delta: positive (increase) or negative (decrease)
     *
     * @return array{stock_balance: StockBalance, journal_entry_id: int, movement_id: int}
     */
    public function adjust(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $item = Item::query()->findOrFail((int) $data['item_id']);
            Warehouse::query()->findOrFail((int) $data['warehouse_id']);

            $delta = (float) $data['quantity_delta'];
            if ($delta === 0.0) {
                throw ValidationException::withMessages([
                    'quantity_delta' => ['Cannot be zero.'],
                ]);
            }

            /** @var StockBalance $balance */
            $balance = StockBalance::query()
                ->where('item_id', $item->id)
                ->where('warehouse_id', (int) $data['warehouse_id'])
                ->lockForUpdate()
                ->firstOrCreate([
                    'item_id' => $item->id,
                    'warehouse_id' => (int) $data['warehouse_id'],
                ], [
                    'quantity' => 0,
                    'avg_cost' => 0,
                ]);

            $oldQty = round((float) $balance->quantity, 4);
            $oldAvg = round((float) $balance->avg_cost, 6);

            if ($delta > 0) {
                if (! isset($data['unit_cost'])) {
                    throw ValidationException::withMessages([
                        'unit_cost' => ['unit_cost is required for positive adjustment.'],
                    ]);
                }

                $qtyIn = round((float) $data['quantity_delta'], 4);
                $unitCost = round((float) $data['unit_cost'], 6);
                if ($qtyIn <= 0 || $unitCost <= 0) {
                    throw ValidationException::withMessages([
                        'quantity_delta' => ['Must be greater than zero.'],
                    ]);
                }

                $totalCost = round($qtyIn * $unitCost, 2);
                $oldValue = round($oldQty * $oldAvg, 2);
                $newQty = round($oldQty + $qtyIn, 4);
                $newAvg = $newQty > 0 ? round(($oldValue + $totalCost) / $newQty, 6) : 0.0;

                $balance->forceFill(['quantity' => $newQty, 'avg_cost' => $newAvg])->save();

                $movement = $this->stockMovementService->record(
                    date: (string) $data['date'],
                    itemId: $item->id,
                    warehouseId: (int) $data['warehouse_id'],
                    type: 'adjustment',
                    qtyIn: $qtyIn,
                    qtyOut: 0,
                    unitCost: $unitCost,
                    referenceType: $data['reference_type'] ?? null,
                    referenceId: isset($data['reference_id']) ? (int) $data['reference_id'] : null,
                )['movement'];

                $journal = $this->journalService->create(
                    new JournalData(
                        date: (string) $data['date'],
                        description: (string) ($data['description'] ?? 'Stock adjustment (increase)'),
                        accounting_period_id: (int) $data['accounting_period_id'],
                        lines: [
                            new JournalLineData(account_id: (int) $item->inventory_account_id, debit: $totalCost, credit: 0),
                            new JournalLineData(account_id: (int) $item->inventory_adjustment_account_id, debit: 0, credit: $totalCost),
                        ],
                    ),
                    reason: 'Stock adjustment',
                );

                return [
                    'stock_balance' => $balance->fresh(),
                    'journal_entry_id' => (int) $journal->id,
                    'movement_id' => (int) $movement->id,
                ];
            }

            $qtyOut = round(abs($delta), 4);
            if ($qtyOut <= 0) {
                throw ValidationException::withMessages([
                    'quantity_delta' => ['Must be non-zero.'],
                ]);
            }

            $newQty = round($oldQty - $qtyOut, 4);
            $allowNegative = AppSetting::getBool(self::KEY_ALLOW_NEGATIVE_STOCK, false);

            if (! $allowNegative && $newQty < 0) {
                throw ValidationException::withMessages([
                    'quantity_delta' => ['Stock cannot be negative.'],
                ]);
            }

            $unitCost = $oldAvg;
            $totalCost = round($qtyOut * $unitCost, 2);

            $balance->forceFill(['quantity' => $newQty, 'avg_cost' => $unitCost])->save();

            $movement = $this->stockMovementService->record(
                date: (string) $data['date'],
                itemId: $item->id,
                warehouseId: (int) $data['warehouse_id'],
                type: 'adjustment',
                qtyIn: 0,
                qtyOut: $qtyOut,
                unitCost: $unitCost,
                referenceType: $data['reference_type'] ?? null,
                referenceId: isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            )['movement'];

            $journal = $this->journalService->create(
                new JournalData(
                    date: (string) $data['date'],
                    description: (string) ($data['description'] ?? 'Stock adjustment (decrease)'),
                    accounting_period_id: (int) $data['accounting_period_id'],
                    lines: [
                        new JournalLineData(account_id: (int) $item->inventory_adjustment_account_id, debit: $totalCost, credit: 0),
                        new JournalLineData(account_id: (int) $item->inventory_account_id, debit: 0, credit: $totalCost),
                    ],
                ),
                reason: 'Stock adjustment',
            );

            return [
                'stock_balance' => $balance->fresh(),
                'journal_entry_id' => (int) $journal->id,
                'movement_id' => (int) $movement->id,
            ];
        });
    }
}
