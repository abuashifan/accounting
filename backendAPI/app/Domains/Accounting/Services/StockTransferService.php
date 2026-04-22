<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\Item;
use App\Domains\Accounting\Models\StockBalance;
use App\Domains\Accounting\Models\Warehouse;
use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    private const KEY_ALLOW_NEGATIVE_STOCK = 'inventory.allow_negative_stock';

    public function __construct(
        private readonly StockMovementService $stockMovementService,
    ) {}

    /**
     * @return array{from_balance: StockBalance, to_balance: StockBalance, movement_out_id: int, movement_in_id: int}
     */
    public function transfer(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $item = Item::query()->findOrFail((int) $data['item_id']);
            Warehouse::query()->findOrFail((int) $data['from_warehouse_id']);
            Warehouse::query()->findOrFail((int) $data['to_warehouse_id']);

            if ((int) $data['from_warehouse_id'] === (int) $data['to_warehouse_id']) {
                throw ValidationException::withMessages([
                    'to_warehouse_id' => ['Destination warehouse must be different.'],
                ]);
            }

            $qty = (float) $data['quantity'];
            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Must be greater than zero.'],
                ]);
            }
            $qty = round($qty, 4);

            /** @var StockBalance $from */
            $from = StockBalance::query()
                ->where('item_id', $item->id)
                ->where('warehouse_id', (int) $data['from_warehouse_id'])
                ->lockForUpdate()
                ->firstOrCreate([
                    'item_id' => $item->id,
                    'warehouse_id' => (int) $data['from_warehouse_id'],
                ], [
                    'quantity' => 0,
                    'avg_cost' => 0,
                ]);

            /** @var StockBalance $to */
            $to = StockBalance::query()
                ->where('item_id', $item->id)
                ->where('warehouse_id', (int) $data['to_warehouse_id'])
                ->lockForUpdate()
                ->firstOrCreate([
                    'item_id' => $item->id,
                    'warehouse_id' => (int) $data['to_warehouse_id'],
                ], [
                    'quantity' => 0,
                    'avg_cost' => 0,
                ]);

            $fromQty = round((float) $from->quantity, 4);
            $unitCost = round((float) $from->avg_cost, 6);

            $allowNegative = AppSetting::getBool(self::KEY_ALLOW_NEGATIVE_STOCK, false);

            if (! $allowNegative && round($fromQty - $qty, 4) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stock cannot be negative.'],
                ]);
            }

            $from->forceFill([
                'quantity' => round($fromQty - $qty, 4),
                'avg_cost' => $unitCost,
            ])->save();

            $toQty = round((float) $to->quantity, 4);
            $toAvg = round((float) $to->avg_cost, 6);
            $toValue = round($toQty * $toAvg, 2);
            $inValue = round($qty * $unitCost, 2);
            $newToQty = round($toQty + $qty, 4);
            $newToAvg = $newToQty > 0 ? round(($toValue + $inValue) / $newToQty, 6) : 0.0;

            $to->forceFill([
                'quantity' => $newToQty,
                'avg_cost' => $newToAvg,
            ])->save();

            $movementOut = $this->stockMovementService->record(
                date: (string) $data['date'],
                itemId: $item->id,
                warehouseId: (int) $data['from_warehouse_id'],
                type: 'transfer_out',
                qtyIn: 0,
                qtyOut: $qty,
                unitCost: $unitCost,
                referenceType: $data['reference_type'] ?? null,
                referenceId: isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            )['movement'];

            $movementIn = $this->stockMovementService->record(
                date: (string) $data['date'],
                itemId: $item->id,
                warehouseId: (int) $data['to_warehouse_id'],
                type: 'transfer_in',
                qtyIn: $qty,
                qtyOut: 0,
                unitCost: $unitCost,
                referenceType: $data['reference_type'] ?? null,
                referenceId: isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            )['movement'];

            return [
                'from_balance' => $from->fresh(),
                'to_balance' => $to->fresh(),
                'movement_out_id' => (int) $movementOut->id,
                'movement_in_id' => (int) $movementIn->id,
            ];
        });
    }
}
