<?php

namespace App\Modules\Accounting\Controllers;

use App\Base\Controller;
use App\Modules\Accounting\Requests\StoreFinancialVoucherRequest;
use App\Modules\Accounting\Services\FinancialVoucherService;
use Illuminate\Http\JsonResponse;

class FinancialVoucherController extends Controller
{
    public function store(StoreFinancialVoucherRequest $request, FinancialVoucherService $service): JsonResponse
    {
        // کنترلر فقط Request را به DTO تبدیل کرده و به Service تحویل می‌دهد
        $dto = $request->toDTO();
        
        $voucher = $service->createVoucher($dto);

        return response()->json([
            'success' => true,
            'message' => 'Financial voucher created successfully.',
            'data' => [
                'voucher_id' => $voucher->voucher_id,
                'status' => $voucher->status,
            ]
        ], 201);
    }
}