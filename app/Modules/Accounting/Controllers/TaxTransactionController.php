<?php

namespace App\Modules\Accounting\Controllers;

use App\Base\Controller;
use App\Modules\Accounting\Requests\StoreTaxTransactionRequest;
use App\Modules\Accounting\Requests\UpdateTaxTransactionRequest;
use App\Modules\Accounting\DTOs\UpdateTaxTransactionDTO;
use App\Modules\Accounting\Services\TaxTransactionService;
use Illuminate\Http\JsonResponse;

class TaxTransactionController extends Controller
{
    public function __construct(private readonly TaxTransactionService $taxService)
    {
    }

    public function index(): JsonResponse
    {
        $transactions = $this->taxService->getAll();

        return response()->json([
            'success' => true,
            'data'    => $transactions,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $tx = $this->taxService->getById($id);

        return response()->json([
            'success' => true,
            'data'    => $tx,
        ]);
    }

    public function store(StoreTaxTransactionRequest $request): JsonResponse
    {
        $dto = $request->toDTO();
        $transaction = $this->taxService->recordTransaction($dto);

        return response()->json([
            'success' => true,
            'message' => 'Tax transaction recorded successfully.',
            'data'    => [
                'transaction_id' => $transaction->transaction_id,
                'tax_amount'     => $transaction->tax_amount,
                'row_version'    => $transaction->row_version,
            ],
        ], 201);
    }

    public function update(UpdateTaxTransactionRequest $request, string $id): JsonResponse
    {
        $dto = UpdateTaxTransactionDTO::fromRequest($request);
        $tx = $this->taxService->updateTransaction($id, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Tax transaction updated successfully.',
            'data'    => $tx,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->taxService->deleteTransaction($id);

        return response()->json([
            'success' => true,
            'message' => 'Tax transaction deleted successfully.',
        ]);
    }
}
