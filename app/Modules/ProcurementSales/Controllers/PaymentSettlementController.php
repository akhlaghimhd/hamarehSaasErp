<?php

namespace App\Modules\ProcurementSales\Controllers;

use App\Base\Controller;
use App\Modules\ProcurementSales\DTOs\RecordCashTransactionDTO;
use App\Modules\ProcurementSales\Requests\RecordCashTransactionRequest;
use App\Modules\ProcurementSales\Services\PaymentSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentSettlementController extends Controller
{
    public function __construct(
        private readonly PaymentSettlementService $settlementService,
    ) {
    }

    public function showSchedule(string $paymentScheduleId): JsonResponse
    {
        $schedule = $this->settlementService->getSchedule($paymentScheduleId);
        return response()->json(['data' => $schedule]);
    }

    public function listForDocument(Request $request): JsonResponse
    {
        $request->validate([
            'source_document_type' => 'required|string|in:SAL_INVOICE,PUR_INVOICE',
            'source_document_id'   => 'required|uuid',
        ]);

        $list = $this->settlementService->listSchedulesForDocument(
            $request->input('source_document_type'),
            $request->input('source_document_id'),
        );

        return response()->json(['data' => $list]);
    }

    public function recordReceipt(RecordCashTransactionRequest $request): JsonResponse
    {
        $dto = RecordCashTransactionDTO::fromRequest($request->validated());
        $tx = $this->settlementService->recordReceipt(
            $dto->paymentScheduleId,
            $dto->bankAccountId,
            $dto->amount,
            $dto->paymentReference,
            $dto->transactionDate,
        );
        return response()->json(['data' => $tx], 201);
    }

    public function recordPayment(RecordCashTransactionRequest $request): JsonResponse
    {
        $dto = RecordCashTransactionDTO::fromRequest($request->validated());
        $tx = $this->settlementService->recordPayment(
            $dto->paymentScheduleId,
            $dto->bankAccountId,
            $dto->amount,
            $dto->paymentReference,
            $dto->transactionDate,
        );
        return response()->json(['data' => $tx], 201);
    }
}
