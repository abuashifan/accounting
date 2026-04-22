<?php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Services\BalanceSheetService;
use App\Domains\Accounting\Services\CashFlowService;
use App\Domains\Accounting\Services\GeneralLedgerService;
use App\Domains\Accounting\Services\ProfitLossService;
use App\Domains\Accounting\Services\StockMovementService;
use App\Domains\Accounting\Services\TrialBalanceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\BalanceSheetReportRequest;
use App\Http\Requests\Accounting\CashFlowReportRequest;
use App\Http\Requests\Accounting\GeneralLedgerReportRequest;
use App\Http\Requests\Accounting\ProfitLossReportRequest;
use App\Http\Requests\Accounting\StockCardReportRequest;
use App\Http\Requests\Accounting\TrialBalanceReportRequest;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function generalLedger(
        GeneralLedgerReportRequest $request,
        GeneralLedgerService $service,
    ): JsonResponse {
        return response()->json([
            'data' => $service->getLedger(
                accountId: (int) $request->validated('account_id'),
                dateFrom: $request->validated('date_from'),
                dateTo: $request->validated('date_to'),
            ),
        ]);
    }

    public function trialBalance(
        TrialBalanceReportRequest $request,
        TrialBalanceService $service,
    ): JsonResponse {
        $periodId = $request->validated('period_id');

        return response()->json([
            'data' => $service->getTrialBalance(
                periodId: $periodId !== null ? (int) $periodId : null,
                dateFrom: $request->validated('date_from'),
                dateTo: $request->validated('date_to'),
            ),
        ]);
    }

    public function profitLoss(
        ProfitLossReportRequest $request,
        ProfitLossService $service,
    ): JsonResponse {
        $periodId = $request->validated('period_id');

        return response()->json([
            'data' => $service->getProfitLoss(
                periodId: $periodId !== null ? (int) $periodId : null,
                dateFrom: $request->validated('date_from'),
                dateTo: $request->validated('date_to'),
            ),
        ]);
    }

    public function balanceSheet(
        BalanceSheetReportRequest $request,
        BalanceSheetService $service,
    ): JsonResponse {
        $periodId = $request->validated('period_id');

        return response()->json([
            'data' => $service->getBalanceSheet(
                periodId: $periodId !== null ? (int) $periodId : null,
                dateFrom: $request->validated('date_from'),
                dateTo: $request->validated('date_to'),
            ),
        ]);
    }

    public function cashFlow(
        CashFlowReportRequest $request,
        CashFlowService $service,
    ): JsonResponse {
        $periodId = $request->validated('period_id');

        return response()->json([
            'data' => $service->getCashFlow(
                periodId: $periodId !== null ? (int) $periodId : null,
                dateFrom: $request->validated('date_from'),
                dateTo: $request->validated('date_to'),
            ),
        ]);
    }

    public function stockCard(
        StockCardReportRequest $request,
        StockMovementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => $service->getStockCard(
                itemId: (int) $request->validated('item_id'),
                warehouseId: $request->validated('warehouse_id') !== null ? (int) $request->validated('warehouse_id') : null,
            ),
        ]);
    }
}
