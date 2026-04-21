<?php

namespace App\Http\Controllers\Debug;

use App\Domains\Accounting\Services\GeneralLedgerService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DebugReportController extends Controller
{
    public function generalLedger(Request $request, GeneralLedgerService $service): View
    {
        $validated = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return view('debug.general-ledger', [
            'accounts' => $service->getSelectableAccounts(),
            'filters' => [
                'account_id' => $validated['account_id'] ?? '',
                'start_date' => $validated['start_date'] ?? '',
                'end_date' => $validated['end_date'] ?? '',
            ],
            'ledger' => isset($validated['account_id'], $validated['start_date'], $validated['end_date'])
                ? $service->getLedger(
                    accountId: (int) $validated['account_id'],
                    startDate: (string) $validated['start_date'],
                    endDate: (string) $validated['end_date'],
                )
                : null,
        ]);
    }

    public function trialBalancePage(): View
    {
        return view('debug.trial-balance', [
            'filters' => [
                'start_date' => request('start_date', ''),
                'end_date' => request('end_date', ''),
            ],
        ]);
    }
}
