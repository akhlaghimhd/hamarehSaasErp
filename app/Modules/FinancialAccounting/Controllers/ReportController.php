<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\BalanceSheetService;
use App\Modules\FinancialAccounting\Application\Services\ProfitAndLossService;
use App\Modules\FinancialAccounting\Application\Services\TrialBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function trialBalance(Request $request, TrialBalanceService $service): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'period_id'  => 'required|uuid',
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $service->run($data['company_id'], $data['period_id']),
        ]);
    }

    public function profitAndLoss(Request $request, ProfitAndLossService $service): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'period_id'  => 'required|uuid',
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $service->run($data['company_id'], $data['period_id']),
        ]);
    }

    public function balanceSheet(Request $request, BalanceSheetService $service): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'period_id'  => 'required|uuid',
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $service->run($data['company_id'], $data['period_id']),
        ]);
    }
}
