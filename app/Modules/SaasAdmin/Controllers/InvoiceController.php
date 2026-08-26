<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Requests\CreateInvoiceRequest;
use App\Modules\SaasAdmin\DTOs\CreateInvoiceDTO;
use App\Modules\SaasAdmin\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {
    }

    public function store(CreateInvoiceRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized.',
            ], 401);
        }

        $dto = CreateInvoiceDTO::fromRequest($request->validated());
        $dueDate = $dto->dueDate ? Carbon::parse($dto->dueDate) : null;

        $invoice = $this->invoiceService->createInvoice(
            $dto->tenantId,
            $dto->items,
            $dto->discountAmount,
            $dto->taxAmount,
            $dueDate,
            $user->user_id
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Invoice created successfully.',
            'data'    => $invoice,
        ], 201);
    }

    public function show(string $invoiceId): JsonResponse
    {
        $user = $this->request()->user();
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized.',
            ], 401);
        }

        $invoice = $this->invoiceService->getInvoiceById($invoiceId, $user);

        if (!$invoice) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invoice not found or access denied.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $invoice,
        ]);
    }
}