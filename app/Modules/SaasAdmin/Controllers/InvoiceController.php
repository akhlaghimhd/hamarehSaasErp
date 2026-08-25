<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Requests\CreateInvoiceRequest;
use App\Modules\SaasAdmin\DTOs\CreateInvoiceDTO;
use App\Modules\SaasAdmin\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {
    }

    public function store(CreateInvoiceRequest $request): JsonResponse
    {
        $userId = Auth::guard('api')->id();
        $dto = CreateInvoiceDTO::fromRequest($request->validated());

        $dueDate = $dto->dueDate ? Carbon::parse($dto->dueDate) : null;

        $invoice = $this->invoiceService->createInvoice(
            $dto->tenantId,
            $dto->items,
            $dto->discountAmount,
            $dto->taxAmount,
            $dueDate,
            $userId
        );

        return response()->json([
            'message' => 'Invoice created successfully.',
            'data'    => $invoice,
        ], 201);
    }
}