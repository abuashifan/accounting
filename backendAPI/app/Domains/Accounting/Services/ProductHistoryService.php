<?php

namespace App\Domains\Accounting\Services;

use App\Models\Invoice;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

class ProductHistoryService
{
    /**
     * @return array{item_id:int,warehouse_id:int|null,entries:list<array{
     *   date:string,
     *   transaction_no:string|null,
     *   source:string,
     *   type:string,
     *   warehouse:array{id:int,code:string,name:string},
     *   qty_in:float,
     *   qty_out:float
     * }>}
     */
    public function getItemHistory(
        int $itemId,
        ?int $warehouseId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $limit = 200,
    ): array {
        $q = StockMovement::query()
            ->with('warehouse:id,code,name')
            ->where('item_id', $itemId)
            ->when($warehouseId !== null, fn ($qb) => $qb->where('warehouse_id', $warehouseId))
            ->when($dateFrom !== null, fn ($qb) => $qb->whereDate('date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($qb) => $qb->whereDate('date', '<=', $dateTo))
            ->orderBy('date')
            ->orderBy('id')
            ->limit($limit);

        /** @var Collection<int, StockMovement> $movements */
        $movements = $q->get();

        $invoiceIds = $movements
            ->filter(fn (StockMovement $m) => $m->reference_type === 'invoice' && $m->reference_id !== null)
            ->map(fn (StockMovement $m) => (int) $m->reference_id)
            ->unique()
            ->values()
            ->all();

        $invoiceNos = $invoiceIds !== []
            ? Invoice::query()->whereIn('id', $invoiceIds)->pluck('invoice_no', 'id')->all()
            : [];

        $entries = $movements->map(function (StockMovement $m) use ($invoiceNos): array {
            $refType = $m->reference_type ? (string) $m->reference_type : null;
            $refId = $m->reference_id !== null ? (int) $m->reference_id : null;

            $transactionNo = null;
            if ($refType === 'invoice' && $refId !== null) {
                $transactionNo = $invoiceNos[$refId] ?? null;
            } elseif ($refType !== null && $refId !== null) {
                $transactionNo = strtoupper($refType).'-'.$refId;
            }

            $source = $refType === 'invoice' ? 'invoice' : (string) $m->type;

            return [
                'date' => $m->date?->format('Y-m-d') ?? '',
                'transaction_no' => $transactionNo,
                'source' => $source,
                'type' => (string) $m->type,
                'warehouse' => [
                    'id' => (int) $m->warehouse_id,
                    'code' => (string) ($m->warehouse?->code ?? ''),
                    'name' => (string) ($m->warehouse?->name ?? ''),
                ],
                'qty_in' => round((float) $m->qty_in, 4),
                'qty_out' => round((float) $m->qty_out, 4),
            ];
        })->values()->all();

        return [
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'entries' => $entries,
        ];
    }
}

