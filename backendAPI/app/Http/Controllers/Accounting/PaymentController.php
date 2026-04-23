<?php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\DTOs\PaymentData;
use App\Domains\Accounting\Services\PaymentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StorePaymentRequest;
use App\Http\Requests\Accounting\UpdatePaymentRequest;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->with(['invoice:id,invoice_no,invoice_date,amount,status,posted_at', 'journalEntry:id,status'])
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'data' => $payments,
        ]);
    }

    public function store(StorePaymentRequest $request, PaymentService $service): JsonResponse
    {
        $v = $request->validated();

        $payment = $service->record(new PaymentData(
            payment_no: (string) $v['payment_no'],
            invoice_id: (int) $v['invoice_id'],
            payment_date: (string) $v['payment_date'],
            amount: (float) $v['amount'],
            description: $v['description'] ?? null,
        ));

        return response()->json([
            'data' => $payment,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        /** @var Payment $payment */
        $payment = Payment::query()
            ->with(['invoice', 'journalEntry.journalLines.account'])
            ->findOrFail($id);

        return response()->json([
            'data' => $payment,
        ]);
    }

    public function update(int $id, UpdatePaymentRequest $request, PaymentService $service): JsonResponse
    {
        $payment = $service->update($id, $request->validated());

        return response()->json([
            'data' => $payment,
        ]);
    }

    public function destroy(int $id, PaymentService $service): JsonResponse
    {
        $service->delete($id);

        return response()->json([
            'data' => ['id' => $id],
        ]);
    }

    public function void(int $id, Request $request, PaymentService $service): JsonResponse
    {
        $validated = $request->validate([
            'void_reason' => ['nullable', 'string'],
        ]);

        $payment = $service->void($id, $validated['void_reason'] ?? null);

        return response()->json([
            'data' => $payment,
        ]);
    }
}

