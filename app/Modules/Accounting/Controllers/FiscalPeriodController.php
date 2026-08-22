<?php

namespace App\Modules\Accounting\Controllers;

use App\Base\Controller;
use App\Modules\Accounting\Requests\StoreFiscalPeriodRequest;
use App\Modules\Accounting\Services\FiscalPeriodService;
use Illuminate\Http\JsonResponse;

class FiscalPeriodController extends Controller
{
    public function store(StoreFiscalPeriodRequest $request, FiscalPeriodService $service): JsonResponse
    {
        $dto = $request->toDTO();
        
        $period = $service->createFiscalPeriod($dto);

        return response()->json([
            'success' => true,
            'message' => 'Fiscal period created successfully.',
            'data' => [
                'period_id' => $period->period_id,
                'name' => $period->name,
                'is_closed' => $period->is_closed,
            ]
        ], 201);
    }
}