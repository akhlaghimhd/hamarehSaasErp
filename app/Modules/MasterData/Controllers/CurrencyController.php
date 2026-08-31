<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use App\Modules\MasterData\Services\CurrencyService;
use App\Modules\MasterData\Requests\CreateCurrencyRequest;
use App\Modules\MasterData\Requests\UpdateCurrencyRequest;
use App\Modules\MasterData\DTOs\CreateCurrencyDTO;
use App\Modules\MasterData\DTOs\UpdateCurrencyDTO;
use Illuminate\Http\JsonResponse;
use Exception;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly CurrencyService $currencyService
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->currencyService->getAll(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->currencyService->getById($id),
        ]);
    }

    public function store(CreateCurrencyRequest $request): JsonResponse
    {
        try {
            $dto = CreateCurrencyDTO::fromRequest($request);
            $currency = $this->currencyService->create($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Currency created successfully.',
                'data'    => $currency,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function update(UpdateCurrencyRequest $request, string $id): JsonResponse
    {
        try {
            $dto = UpdateCurrencyDTO::fromRequest($request);
            $currency = $this->currencyService->update($id, $dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Currency updated successfully.',
                'data'    => $currency,
            ]);
        } catch (Exception $e) {
            $status = str_contains($e->getMessage(), 'No query results') ? 404 : 422;

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->currencyService->delete($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'Currency deleted successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
