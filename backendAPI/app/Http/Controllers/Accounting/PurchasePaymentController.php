<?php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\DTOs\PurchasePaymentData;
use App\Domains\Accounting\Services\PurchasePaymentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StorePurchasePaymentRequest;
use App\Http\Requests\Accounting\UpdatePurchasePaymentRequest;
use App\Models\PurchasePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchasePaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payments = PurchasePayment::query()
            ->with(['purchaseInvoice:id,invoice_no,invoice_date,amount,status', 'journalEntry:id,status'])
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'data' => $payments,
        ]);
    }

    public function store(StorePurchasePaymentRequest $request, PurchasePaymentService $service): JsonResponse
    {
        $v = $request->validated();

        $payment = $service->record(new PurchasePaymentData(
            payment_no: (string) $v['payment_no'],
            purchase_invoice_id: (int) $v['purchase_invoice_id'],
            payment_date: (string) $v['payment_date'],
            amount: (float) $v['amount'],
            credit_account_id: (int) $v['credit_account_id'],
            description: $v['description'] ?? null,
        ));

        return response()->json([
            'data' => $payment,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        /** @var PurchasePayment $payment */
        $payment = PurchasePayment::query()
            ->with(['purchaseInvoice', 'journalEntry.journalLines.account'])
            ->findOrFail($id);

        return response()->json([
            'data' => $payment,
        ]);
    }

    public function update(int $id, UpdatePurchasePaymentRequest $request, PurchasePaymentService $service): JsonResponse
    {
        $payment = $service->update($id, $request->validated());

        return response()->json([
            'data' => $payment,
        ]);
    }

    public function destroy(int $id, PurchasePaymentService $service): JsonResponse
    {
        $service->delete($id);

        return response()->json([
            'data' => ['id' => $id],
        ]);
    }
}
