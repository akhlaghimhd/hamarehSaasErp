<?php

namespace App\Modules\Accounting\Controllers;

use App\Base\Controller;
use App\Modules\Accounting\Requests\StoreTaxTransactionRequest;
use App\Modules\Accounting\Services\TaxTransactionService;
use Illuminate\Http\JsonResponse;

class TaxTransactionController extends Controller
{
    public function store(StoreTaxTransactionRequest $request, TaxTransactionService $service): JsonResponse
    {
        $dto = $request->toDTO();
        
        $transaction = $service->recordTransaction($dto);

        return response()->json([
            'success' => true,
            'message' => 'Tax transaction recorded successfully.',
            'data' => [
                'transaction_id' => $transaction->transaction_id,
                'tax_amount' => $transaction->tax_amount,
            ]
        ], 201);
    }
}