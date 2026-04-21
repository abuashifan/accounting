<?php

namespace App\Http\Controllers\Debug;

use App\Domains\Accounting\DTOs\InvoiceData;
use App\Domains\Accounting\Services\InvoiceService;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class DebugInvoiceController extends Controller
{
    public function index(): View
    {
        return view('debug.invoices.index');
    }

    public function create(): View
    {
        return view('debug.invoices.create');
    }

    public function list(Request $request): JsonResponse
    {
        $invoices = Invoice::query()
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $invoices,
            'message' => 'OK',
        ]);
    }

    public function store(Request $request, InvoiceService $service): JsonResponse
    {
        try {
            $validated = $request->validate([
                'invoice_no' => ['required', 'string', 'max:50'],
                'invoice_date' => ['required', 'date'],
                'amount' => ['required', 'numeric'],
                'description' => ['nullable', 'string'],
            ]);

            $dto = new InvoiceData(
                invoice_no: (string) $validated['invoice_no'],
                invoice_date: (string) $validated['invoice_date'],
                amount: (float) $validated['amount'],
                description: $validated['description'] ?? null,
            );

            $invoice = $service->create($dto);

            return response()->json([
                'success' => true,
                'data' => $invoice,
                'message' => 'Invoice created',
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

