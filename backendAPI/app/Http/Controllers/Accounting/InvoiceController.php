<?php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Services\InvoiceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreSalesInvoiceRequest;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::query()
            ->with(['journalEntry:id,status', 'invoiceLines'])
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'data' => $invoices,
        ]);
    }

    public function store(StoreSalesInvoiceRequest $request, InvoiceService $service): JsonResponse
    {
        $invoice = $service->createSales($request->validated());

        return response()->json([
            'data' => $invoice,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::query()
            ->with(['journalEntry.journalLines.account', 'invoiceLines.item', 'invoiceLines.warehouse', 'payments'])
            ->findOrFail($id);

        return response()->json([
            'data' => $invoice,
        ]);
    }

    public function post(int $id, InvoiceService $service): JsonResponse
    {
        $invoice = $service->postSales($id);

        return response()->json([
            'data' => $invoice,
        ]);
    }
}

