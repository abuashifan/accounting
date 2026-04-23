<?php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Services\StockAdjustmentService;
use App\Domains\Accounting\Services\StockService;
use App\Domains\Accounting\Services\StockTransferService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StockAdjustmentRequest;
use App\Http\Requests\Accounting\StockPurchaseRequest;
use App\Http\Requests\Accounting\StockTransferRequest;
use App\Http\Requests\Accounting\StoreWarehouseRequest;
use App\Http\Requests\Accounting\UpdateWarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;

class StockController extends Controller
{
    public function warehousesIndex(): JsonResponse
    {
        return response()->json([
            'data' => Warehouse::query()->orderBy('code')->get(),
        ]);
    }

    public function warehousesStore(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::query()->create($request->validated());

        return response()->json([
            'data' => $warehouse,
        ], 201);
    }

    public function warehousesShow(int $id): JsonResponse
    {
        return response()->json([
            'data' => Warehouse::query()->findOrFail($id),
        ]);
    }

    public function warehousesUpdate(int $id, UpdateWarehouseRequest $request): JsonResponse
    {
        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()->findOrFail($id);
        $warehouse->fill($request->validated())->save();

        return response()->json([
            'data' => $warehouse->fresh(),
        ]);
    }

    public function adjustment(StockAdjustmentRequest $request, StockAdjustmentService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->adjust($request->validated()),
        ]);
    }

    public function purchase(StockPurchaseRequest $request, StockService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->purchase($request->validated()),
        ]);
    }

    public function transfer(StockTransferRequest $request, StockTransferService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->transfer($request->validated()),
        ]);
    }
}
