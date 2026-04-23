<?php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Services\SalesReturnService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreSalesReturnRequest;
use App\Http\Requests\Accounting\UpdateSalesReturnRequest;
use App\Models\SalesReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesReturnController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $returns = SalesReturn::query()
            ->with(['invoice:id,invoice_no,invoice_date,amount,status,posted_at', 'journalEntry:id,status', 'salesReturnLines'])
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'data' => $returns,
        ]);
    }

    public function store(StoreSalesReturnRequest $request, SalesReturnService $service): JsonResponse
    {
        $return = $service->create($request->validated());

        return response()->json([
            'data' => $return,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        /** @var SalesReturn $return */
        $return = SalesReturn::query()
            ->with(['invoice', 'salesReturnLines.item', 'salesReturnLines.warehouse', 'journalEntry.journalLines.account'])
            ->findOrFail($id);

        return response()->json([
            'data' => $return,
        ]);
    }

    public function update(int $id, UpdateSalesReturnRequest $request, SalesReturnService $service): JsonResponse
    {
        $return = $service->update($id, $request->validated());

        return response()->json([
            'data' => $return,
        ]);
    }

    public function destroy(int $id, SalesReturnService $service): JsonResponse
    {
        $service->delete($id);

        return response()->json([
            'data' => ['id' => $id],
        ]);
    }

    public function post(int $id, SalesReturnService $service): JsonResponse
    {
        $return = $service->post($id);

        return response()->json([
            'data' => $return,
        ]);
    }

    public function void(int $id, Request $request, SalesReturnService $service): JsonResponse
    {
        $validated = $request->validate([
            'void_reason' => ['nullable', 'string'],
        ]);

        $return = $service->void($id, $validated['void_reason'] ?? null);

        return response()->json([
            'data' => $return,
        ]);
    }
}
