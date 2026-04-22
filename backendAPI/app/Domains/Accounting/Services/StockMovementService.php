<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\StockMovement;

class StockMovementService
{
    /**
     * @return array{movement: StockMovement}
     */
    public function record(
        string $date,
        int $itemId,
        int $warehouseId,
        string $type,
        float $qtyIn,
        float $qtyOut,
        float $unitCost,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): array {
        $qtyIn = round($qtyIn, 4);
        $qtyOut = round($qtyOut, 4);
        $unitCost = round($unitCost, 6);
        $totalCost = round(($qtyIn + $qtyOut) * $unitCost, 2);

        /** @var StockMovement $movement */
        $movement = StockMovement::query()->create([
            'date' => $date,
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);

        return ['movement' => $movement];
    }

    /**
     * Stock Card (Kartu Stok) - audit trail from movements.
     *
     * @return array{item_id:int,warehouse_id:int|null,entries:list<array{date:string,type:string,qty_in:float,qty_out:float,balance:float}>}
     */
    public function getStockCard(int $itemId, ?int $warehouseId = null): array
    {
        $movements = StockMovement::query()
            ->where('item_id', $itemId)
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderBy('date')
            ->orderBy('id')
            ->get(['date', 'type', 'qty_in', 'qty_out']);

        $balance = 0.0;

        $entries = $movements->map(function (StockMovement $movement) use (&$balance): array {
            $qtyIn = round((float) $movement->qty_in, 4);
            $qtyOut = round((float) $movement->qty_out, 4);
            $balance = round($balance + $qtyIn - $qtyOut, 4);

            return [
                'date' => $movement->date?->format('Y-m-d') ?? '',
                'type' => (string) $movement->type,
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'balance' => $balance,
            ];
        })->values()->all();

        return [
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'entries' => $entries,
        ];
    }
}

