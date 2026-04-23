<?php

namespace App\Http\Controllers\Debug;

use App\Domains\Accounting\DTOs\PaymentData;
use App\Domains\Accounting\Services\PaymentService;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class DebugPaymentController extends Controller
{
    public function index(): View
    {
        return view('debug.payments.index');
    }

    public function create(): View
    {
        return view('debug.payments.create');
    }

    public function edit(int $id): View
    {
        return view('debug.payments.edit', ['id' => $id]);
    }

    public function list(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->with(['invoice'])
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $payments,
            'message' => 'OK',
        ]);
    }

    public function store(Request $request, PaymentService $service): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_no' => ['required', 'string', 'max:50'],
                'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
                'payment_date' => ['required', 'date'],
                'amount' => ['required', 'numeric'],
                'description' => ['nullable', 'string'],
            ]);

            $dto = new PaymentData(
                payment_no: (string) $validated['payment_no'],
                invoice_id: (int) $validated['invoice_id'],
                payment_date: (string) $validated['payment_date'],
                amount: (float) $validated['amount'],
                description: $validated['description'] ?? null,
            );

            $payment = $service->record($dto);

            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Payment recorded',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Server error',
            ], 500);
        }
    }
}
