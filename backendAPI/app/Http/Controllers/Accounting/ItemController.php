<?php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Services\ItemService;
use App\Domains\Accounting\Services\ProductHistoryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ProductHistoryRequest;
use App\Http\Requests\Accounting\StoreItemRequest;
use App\Http\Requests\Accounting\UpdateItemRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request, ItemService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->list(
                perPage: (int) $request->integer('per_page', 50),
                includeStock: $request->boolean('include_stock', false),
            ),
        ]);
    }

    public function store(StoreItemRequest $request, ItemService $service): JsonResponse
    {
        $item = $service->create($request->validated());

        return response()->json([
            'data' => $item,
        ], 201);
    }

    public function show(int $id, ItemService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->find($id),
        ]);
    }

    public function update(int $id, UpdateItemRequest $request, ItemService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->update($id, $request->validated()),
        ]);
    }

    public function history(int $id, ProductHistoryRequest $request, ProductHistoryService $service): JsonResponse
    {
        $v = $request->validated();

        return response()->json([
            'data' => $service->getItemHistory(
                itemId: $id,
                warehouseId: isset($v['warehouse_id']) ? (int) $v['warehouse_id'] : null,
                dateFrom: $v['date_from'] ?? null,
                dateTo: $v['date_to'] ?? null,
                limit: isset($v['limit']) ? (int) $v['limit'] : 200,
            ),
        ]);
    }
}
