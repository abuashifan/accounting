<?php

namespace App\Domains\Accounting\Services;

use App\Models\AppSetting;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockReversalService
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
    ) {}

    /**
     * Reverse stock effects based on recorded stock movements for a reference.
     *
     * - If $reversalMovementType is provided, an opposite movement is recorded for each reversed movement.
     * - If $deleteOriginalMovements is true, original movements are deleted after reversal.
     *
     * @param  list<string>  $movementTypes
     */
    public function reverseReference(
        string $referenceType,
        int $referenceId,
        array $movementTypes,
        bool $deleteOriginalMovements = false,
        ?string $reversalMovementType = null,
    ): void {
        DB::transaction(function () use ($referenceType, $referenceId, $movementTypes, $deleteOriginalMovements, $reversalMovementType): void {
            $allowNegative = AppSetting::getBool('inventory.allow_negative_stock', false);

            $movements = StockMovement::query()
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->when(count($movementTypes) > 0, fn ($q) => $q->whereIn('type', $movementTypes))
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            foreach ($movements as $movement) {
                $itemId = (int) $movement->item_id;
                $warehouseId = (int) $movement->warehouse_id;
                $qtyIn = round((float) $movement->qty_in, 4);
                $qtyOut = round((float) $movement->qty_out, 4);
                $unitCost = round((float) $movement->unit_cost, 6);

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

                // Reverse incoming: subtract qty_in using the movement's unit cost.
                if ($qtyIn > 0) {
                    $removeValue = round($qtyIn * $unitCost, 2);
                    $newQty = round($oldQty - $qtyIn, 4);

                    if (! $allowNegative && $newQty < 0) {
                        throw ValidationException::withMessages([
                            'quantity' => ['Stock cannot be negative (reversal would make it negative).'],
                        ]);
                    }

                    $newAvg = $newQty > 0 ? round(($oldValue - $removeValue) / $newQty, 6) : 0.0;

                    $balance->forceFill([
                        'quantity' => $newQty,
                        'avg_cost' => $newAvg,
                    ])->save();

                    // Update local snapshot for possible qty_out reversal below.
                    $oldQty = $newQty;
                    $oldAvg = $newAvg;
                    $oldValue = round($oldQty * $oldAvg, 2);
                }

                // Reverse outgoing: add qty_out back using the movement's unit cost.
                if ($qtyOut > 0) {
                    $addValue = round($qtyOut * $unitCost, 2);
                    $newQty = round($oldQty + $qtyOut, 4);
                    $newAvg = $newQty > 0 ? round(($oldValue + $addValue) / $newQty, 6) : 0.0;

                    $balance->forceFill([
                        'quantity' => $newQty,
                        'avg_cost' => $newAvg,
                    ])->save();
                }

                if ($reversalMovementType !== null) {
                    $this->stockMovementService->record(
                        date: (string) $movement->date?->format('Y-m-d'),
                        itemId: $itemId,
                        warehouseId: $warehouseId,
                        type: $reversalMovementType,
                        qtyIn: $qtyOut,
                        qtyOut: $qtyIn,
                        unitCost: $unitCost,
                        referenceType: $referenceType,
                        referenceId: $referenceId,
                    );
                }
            }

            if ($deleteOriginalMovements && $movements->count() > 0) {
                StockMovement::query()
                    ->whereKey($movements->modelKeys())
                    ->delete();
            }
        });
    }
}

