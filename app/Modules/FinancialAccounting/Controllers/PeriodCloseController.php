<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\PeriodCloseChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodCloseController extends Controller
{
    public function __construct(
        private readonly PeriodCloseChecklistService $checklist
    ) {
    }

    public function evaluate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'period_id'  => 'required|uuid',
        ]);

        $result = $this->checklist->evaluate(
            $data['company_id'],
            $data['period_id'],
            $request->user()?->id
        );

        return response()->json([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    public function softClose(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'period_id'  => 'required|uuid',
        ]);

        $control = $this->checklist->softCloseGuided(
            $data['company_id'],
            $data['period_id'],
            $request->user()?->id
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'دوره نیمه‌بسته شد.',
            'data'    => $control,
        ]);
    }

    public function hardClose(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'period_id'  => 'required|uuid',
        ]);

        $control = $this->checklist->hardCloseGuided(
            $data['company_id'],
            $data['period_id'],
            $request->user()?->id
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'دوره قطعی بسته شد.',
            'data'    => $control,
        ]);
    }
}
