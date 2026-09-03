<?php

namespace App\Modules\Accounting\Controllers;

use App\Base\Controller;
use App\Modules\Accounting\Requests\StoreFiscalPeriodRequest;
use App\Modules\Accounting\Requests\UpdateFiscalPeriodRequest;
use App\Modules\Accounting\DTOs\UpdateFiscalPeriodDTO;
use App\Modules\Accounting\Services\FiscalPeriodService;
use Illuminate\Http\JsonResponse;

class FiscalPeriodController extends Controller
{
    public function __construct(private readonly FiscalPeriodService $fiscalPeriodService)
    {
    }

    public function index(): JsonResponse
    {
        $periods = $this->fiscalPeriodService->getAll();

        return response()->json([
            'success' => true,
            'data'    => $periods,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $period = $this->fiscalPeriodService->getById($id);

        return response()->json([
            'success' => true,
            'data'    => $period,
        ]);
    }

    public function store(StoreFiscalPeriodRequest $request): JsonResponse
    {
        $dto = $request->toDTO();
        $period = $this->fiscalPeriodService->createFiscalPeriod($dto);

        return response()->json([
            'success' => true,
            'message' => 'Fiscal period created successfully.',
            'data'    => [
                'period_id'   => $period->period_id,
                'name'        => $period->name,
                'start_date'  => $period->start_date->toDateString(),
                'end_date'    => $period->end_date->toDateString(),
                'is_closed'   => $period->is_closed,
                'row_version' => $period->row_version,
            ],
        ], 201);
    }

    public function update(UpdateFiscalPeriodRequest $request, string $id): JsonResponse
    {
        $dto = UpdateFiscalPeriodDTO::fromRequest($request);
        $period = $this->fiscalPeriodService->updateFiscalPeriod($id, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Fiscal period updated successfully.',
            'data'    => $period,
        ]);
    }

    public function close(string $id): JsonResponse
    {
        $period = $this->fiscalPeriodService->closePeriod($id);

        return response()->json([
            'success' => true,
            'message' => 'Fiscal period closed successfully.',
            'data'    => $period,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->fiscalPeriodService->deleteFiscalPeriod($id);

        return response()->json([
            'success' => true,
            'message' => 'Fiscal period deleted successfully.',
        ]);
    }
}
