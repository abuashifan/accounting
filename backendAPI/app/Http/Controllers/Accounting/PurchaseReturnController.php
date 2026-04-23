<?php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Services\PurchaseReturnService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StorePurchaseReturnRequest;
use App\Http\Requests\Accounting\UpdatePurchaseReturnRequest;
use App\Models\PurchaseReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $returns = PurchaseReturn::query()
            ->with(['purchaseInvoice:id,invoice_no,invoice_date,amount,status,posted_at', 'journalEntry:id,status', 'purchaseReturnLines'])
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'data' => $returns,
        ]);
    }

    public function store(StorePurchaseReturnRequest $request, PurchaseReturnService $service): JsonResponse
    {
        $return = $service->create($request->validated());

        return response()->json([
            'data' => $return,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        /** @var PurchaseReturn $return */
        $return = PurchaseReturn::query()
            ->with(['purchaseInvoice', 'purchaseReturnLines.item', 'purchaseReturnLines.warehouse', 'journalEntry.journalLines.account'])
            ->findOrFail($id);

        return response()->json([
            'data' => $return,
        ]);
    }

    public function update(int $id, UpdatePurchaseReturnRequest $request, PurchaseReturnService $service): JsonResponse
    {
        $return = $service->update($id, $request->validated());

        return response()->json([
            'data' => $return,
        ]);
    }

    public function destroy(int $id, PurchaseReturnService $service): JsonResponse
    {
        $service->delete($id);

        return response()->json([
            'data' => ['id' => $id],
        ]);
    }

    public function post(int $id, PurchaseReturnService $service): JsonResponse
    {
        $return = $service->post($id);

        return response()->json([
            'data' => $return,
        ]);
    }
}

