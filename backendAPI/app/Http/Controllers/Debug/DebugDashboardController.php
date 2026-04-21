<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class DebugDashboardController extends Controller
{
    public function index(): View
    {
        return view('debug.dashboard');
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_journal' => JournalEntry::query()->count(),
                'total_posted_journal' => JournalEntry::query()->where('status', 'posted')->count(),
                'total_invoice' => Invoice::query()->count(),
                'total_payment' => Payment::query()->count(),
            ],
            'message' => 'OK',
        ]);
    }
}

