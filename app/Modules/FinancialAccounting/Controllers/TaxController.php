<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\TaxReportingService;
use App\Modules\FinancialAccounting\Application\Services\VatCalculationService;
use App\Modules\FinancialAccounting\Infrastructure\Models\TaxRateConfig;
use App\Modules\FinancialAccounting\Infrastructure\Models\TaxTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function __construct(
        private readonly VatCalculationService $vat,
        private readonly TaxReportingService $reports = new TaxReportingService()
    ) {
    }

    public function rates(): JsonResponse
    {
        $rows = TaxRateConfig::query()->orderBy('tax_code')->orderByDesc('valid_from')->limit(200)->get();

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function storeRate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tax_code'     => 'required|string|max:40',
            'name'         => 'required|string|max:150',
            'rate_percent' => 'required|numeric|min:0|max:100',
            'valid_from'   => 'required|date',
            'valid_to'     => 'nullable|date',
            'is_default'   => 'sometimes|boolean',
        ]);

        $row = $this->vat->upsertRate($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'نرخ مالیات ثبت شد.',
            'data'    => $row,
        ], 201);
    }

    public function split(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tax_code'     => 'required|string|max:40',
            'on_date'      => 'required|date',
            'net_amount'   => 'nullable|numeric|min:0',
            'gross_amount' => 'nullable|numeric|min:0',
        ]);

        if (isset($data['gross_amount'])) {
            $split = $this->vat->splitGross((float) $data['gross_amount'], $data['tax_code'], $data['on_date']);
        } elseif (isset($data['net_amount'])) {
            $split = $this->vat->splitNet((float) $data['net_amount'], $data['tax_code'], $data['on_date']);
        } else {
            return response()->json([
                'status'  => 'error',
                'message' => 'net_amount یا gross_amount لازم است.',
            ], 422);
        }

        return response()->json(['status' => 'success', 'data' => $split]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $q = TaxTransaction::query()->orderByDesc('transaction_date');
        if ($request->filled('company_id')) {
            $q->where('company_id', $request->string('company_id'));
        }

        return response()->json(['status' => 'success', 'data' => $q->limit(200)->get()]);
    }

    public function recordTransaction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'           => 'required|uuid',
            'source_document_type' => 'required|string|max:100',
            'source_document_id'   => 'required|uuid',
            'tax_code'             => 'required|string|max:40',
            'transaction_date'     => 'required|date',
            'net_amount'           => 'nullable|numeric|min:0',
            'gross_amount'         => 'nullable|numeric|min:0',
            'direction'            => 'sometimes|in:OUTPUT,INPUT',
            'journal_entry_id'     => 'nullable|uuid',
        ]);

        $row = $this->vat->recordTransaction($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'تراکنش مالیاتی ثبت شد.',
            'data'    => $row,
        ], 201);
    }

    /** FIN-P2-07 */
    public function vatSummary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'from'       => 'required|date',
            'to'         => 'required|date',
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $this->reports->vatPeriodSummary($data['company_id'], $data['from'], $data['to']),
        ]);
    }

    /** FIN-P2-06 */
    public function moodianRecon(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'from'       => 'required|date',
            'to'         => 'required|date',
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $this->reports->moodianLedgerRecon($data['company_id'], $data['from'], $data['to']),
        ]);
    }
}
