<?php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Services\JournalService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreJournalRequest;
use App\Http\Requests\Accounting\UpdateJournalRequest;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = JournalEntry::query()
            ->with(['journalLines.account', 'accountingPeriod'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(20),
            'message' => 'OK',
        ]);
    }

    public function store(StoreJournalRequest $request, JournalService $service): JsonResponse
    {
        $dto = new JournalData(
            date: $request->string('date')->toString(),
            description: $request->input('description'),
            accounting_period_id: (int) $request->input('accounting_period_id'),
            lines: array_map(
                fn (array $line) => new JournalLineData(
                    account_id: (int) $line['account_id'],
                    debit: (float) $line['debit'],
                    credit: (float) $line['credit'],
                ),
                $request->input('lines', []),
            ),
        );

        $journal = $service->create($dto, $request->input('reason'));

        return response()->json([
            'success' => true,
            'data' => $journal,
            'message' => 'Journal created',
        ], 201);
    }

    public function update(int $id, UpdateJournalRequest $request, JournalService $service): JsonResponse
    {
        $dto = new JournalData(
            date: $request->string('date')->toString(),
            description: $request->input('description'),
            accounting_period_id: (int) $request->input('accounting_period_id'),
            lines: array_map(
                fn (array $line) => new JournalLineData(
                    account_id: (int) $line['account_id'],
                    debit: (float) $line['debit'],
                    credit: (float) $line['credit'],
                ),
                $request->input('lines', []),
            ),
        );

        $journal = $service->update($id, $dto, $request->input('reason'));

        return response()->json([
            'success' => true,
            'data' => $journal,
            'message' => 'Journal updated',
        ]);
    }

    public function void(int $id, Request $request, JournalService $service): JsonResponse
    {
        $journal = $service->void($id, $request->input('reason'));

        return response()->json([
            'success' => true,
            'data' => $journal,
            'message' => 'Journal voided',
        ]);
    }

    public function post(int $id, Request $request, JournalService $service): JsonResponse
    {
        $journal = $service->post($id, $request->input('reason'));

        return response()->json([
            'success' => true,
            'data' => $journal,
            'message' => 'Journal posted',
        ]);
    }
}
