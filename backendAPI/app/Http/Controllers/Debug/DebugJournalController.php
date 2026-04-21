<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class DebugJournalController extends Controller
{
    public function index(): View
    {
        return view('debug.journals.index');
    }

    public function create(): View
    {
        return view('debug.journals.create');
    }

    public function edit(int $id): View
    {
        return view('debug.journals.edit', [
            'id' => $id,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $journal = JournalEntry::query()
            ->with(['journalLines.account', 'accountingPeriod'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $journal,
            'message' => 'OK',
        ]);
    }
}

