<?php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\DTOs\PurchasePaymentData;
use App\Domains\Accounting\Services\PurchaseInvoiceService;
use App\Domains\Accounting\Services\PurchasePaymentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\RecordPurchasePaymentRequest;
use App\Http\Requests\Accounting\StorePurchaseInvoiceRequest;
use App\Http\Requests\Accounting\UpdatePurchaseInvoiceRequest;
use App\Models\PurchaseInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $invoices = PurchaseInvoice::query()
            ->with(['journalEntry:id,status', 'purchaseInvoiceLines'])
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'data' => $invoices,
        ]);
    }

    public function store(StorePurchaseInvoiceRequest $request, PurchaseInvoiceService $service): JsonResponse
    {
        $invoice = $service->create($request->validated());

        return response()->json([
            'data' => $invoice,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        /** @var PurchaseInvoice $invoice */
        $invoice = PurchaseInvoice::query()
            ->with(['journalEntry.journalLines.account', 'purchaseInvoiceLines.item', 'purchaseInvoiceLines.warehouse', 'purchasePayments'])
            ->findOrFail($id);

        return response()->json([
            'data' => $invoice,
        ]);
    }

    public function post(int $id, PurchaseInvoiceService $service): JsonResponse
    {
        $invoice = $service->post($id);

        return response()->json([
            'data' => $invoice,
        ]);
    }

    public function update(int $id, UpdatePurchaseInvoiceRequest $request, PurchaseInvoiceService $service): JsonResponse
    {
        $invoice = $service->update($id, $request->validated());

        return response()->json([
            'data' => $invoice,
        ]);
    }

    public function destroy(int $id, PurchaseInvoiceService $service): JsonResponse
    {
        $service->delete($id);

        return response()->json([
            'data' => ['id' => $id],
        ]);
    }

    public function void(int $id, Request $request, PurchaseInvoiceService $service): JsonResponse
    {
        $validated = $request->validate([
            'void_reason' => ['nullable', 'string'],
        ]);

        $invoice = $service->void($id, $validated['void_reason'] ?? null);

        return response()->json([
            'data' => $invoice,
        ]);
    }

    public function recordPayment(
        int $id,
        RecordPurchasePaymentRequest $request,
        PurchasePaymentService $service,
    ): JsonResponse {
        $v = $request->validated();

        $payment = $service->record(new PurchasePaymentData(
            payment_no: (string) $v['payment_no'],
            purchase_invoice_id: $id,
            payment_date: (string) $v['payment_date'],
            amount: (float) $v['amount'],
            credit_account_id: (int) $v['credit_account_id'],
            description: $v['description'] ?? null,
        ));

        return response()->json([
            'data' => $payment,
        ], 201);
    }
}
