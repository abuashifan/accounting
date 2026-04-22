<?php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Services\ItemService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreItemRequest;
use App\Http\Requests\Accounting\UpdateItemRequest;
use Illuminate\Http\JsonResponse;

class ItemController extends Controller
{
    public function index(ItemService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->list(),
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
}

