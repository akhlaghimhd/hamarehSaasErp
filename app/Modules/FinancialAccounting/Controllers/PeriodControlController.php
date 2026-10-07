<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\FiscalPeriodControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodControlController extends Controller
{
    public function __construct(
        private readonly FiscalPeriodControlService $service
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'period_id'  => 'required|uuid',
        ]);

        $row = $this->service->getOrCreate($data['company_id'], $data['period_id']);

        return response()->json(['status' => 'success', 'data' => $row]);
    }

    public function softClose(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'period_id'  => 'required|uuid',
        ]);

        $row = $this->service->softClose(
            $data['company_id'],
            $data['period_id'],
            auth()->id() ? (string) auth()->id() : null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'دوره نیمه‌بسته شد.',
            'data'    => $row,
        ]);
    }

    public function hardClose(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'period_id'  => 'required|uuid',
        ]);

        $row = $this->service->hardClose(
            $data['company_id'],
            $data['period_id'],
            auth()->id() ? (string) auth()->id() : null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'دوره قطعی بسته شد.',
            'data'    => $row,
        ]);
    }

    public function reopen(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'period_id'  => 'required|uuid',
        ]);

        $row = $this->service->reopen($data['company_id'], $data['period_id']);

        return response()->json([
            'status'  => 'success',
            'message' => 'دوره بازگشایی شد.',
            'data'    => $row,
        ]);
    }
}
