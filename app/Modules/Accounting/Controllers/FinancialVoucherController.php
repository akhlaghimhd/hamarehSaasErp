<?php

namespace App\Modules\Accounting\Controllers;

use App\Base\Controller;
use App\Modules\Accounting\Requests\StoreFinancialVoucherRequest;
use App\Modules\Accounting\Requests\UpdateFinancialVoucherRequest;
use App\Modules\Accounting\DTOs\UpdateFinancialVoucherDTO;
use App\Modules\Accounting\Services\FinancialVoucherService;
use Illuminate\Http\JsonResponse;

class FinancialVoucherController extends Controller
{
    public function __construct(private readonly FinancialVoucherService $voucherService)
    {
    }

    public function index(): JsonResponse
    {
        $vouchers = $this->voucherService->getAll();

        return response()->json([
            'success' => true,
            'data'    => $vouchers,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $voucher = $this->voucherService->getById($id);

        return response()->json([
            'success' => true,
            'data'    => $voucher,
        ]);
    }

    public function store(StoreFinancialVoucherRequest $request): JsonResponse
    {
        $dto = $request->toDTO();
        $voucher = $this->voucherService->createVoucher($dto);

        return response()->json([
            'success' => true,
            'message' => 'Financial voucher created successfully.',
            'data'    => [
                'voucher_id'       => $voucher->voucher_id,
                'reference_number' => $voucher->reference_number,
                'status'           => $voucher->status,
                'row_version'      => $voucher->row_version,
            ],
        ], 201);
    }

    public function update(UpdateFinancialVoucherRequest $request, string $id): JsonResponse
    {
        $dto = UpdateFinancialVoucherDTO::fromRequest($request);
        $voucher = $this->voucherService->updateVoucher($id, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Financial voucher updated successfully.',
            'data'    => $voucher,
        ]);
    }

    public function post(string $id): JsonResponse
    {
        $voucher = $this->voucherService->postVoucher($id);

        return response()->json([
            'success' => true,
            'message' => 'Financial voucher posted successfully.',
            'data'    => $voucher,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->voucherService->deleteVoucher($id);

        return response()->json([
            'success' => true,
            'message' => 'Financial voucher deleted successfully.',
        ]);
    }
}
