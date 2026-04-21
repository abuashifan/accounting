<?php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Services\GeneralLedgerService;
use App\Domains\Accounting\Services\TrialBalanceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\GeneralLedgerReportRequest;
use App\Http\Requests\Accounting\TrialBalanceReportRequest;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function generalLedger(
        GeneralLedgerReportRequest $request,
        GeneralLedgerService $service,
    ): JsonResponse {
        $report = $service->getLedger(
            accountId: (int) $request->validated('account_id'),
            startDate: (string) $request->validated('start_date'),
            endDate: (string) $request->validated('end_date'),
        );

        return response()->json([
            'success' => true,
            'data' => $report,
            'message' => 'OK',
        ]);
    }

    public function trialBalance(
        TrialBalanceReportRequest $request,
        TrialBalanceService $service,
    ): JsonResponse {
        $report = $service->getTrialBalance(
            startDate: (string) $request->validated('start_date'),
            endDate: (string) $request->validated('end_date'),
        );

        return response()->json([
            'success' => true,
            'data' => $report,
            'message' => 'OK',
        ]);
    }
}
