<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\TreasuryService;
use App\Modules\FinancialAccounting\Infrastructure\Models\CashAccount;
use App\Modules\FinancialAccounting\Infrastructure\Models\TreasuryDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreasuryController extends Controller
{
    public function __construct(
        private readonly TreasuryService $service
    ) {
    }

    public function cashAccounts(Request $request): JsonResponse
    {
        $q = CashAccount::query()->orderBy('code');
        if ($request->filled('company_id')) {
            $q->where('company_id', $request->string('company_id'));
        }

        return response()->json(['status' => 'success', 'data' => $q->limit(200)->get()]);
    }

    public function storeCashAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'      => 'required|uuid',
            'gl_account_id'   => 'required|uuid',
            'code'            => 'required|string|max:30',
            'name'            => 'required|string|max:150',
            'bank_account_id' => 'nullable|uuid',
            'cash_kind'       => 'sometimes|string|in:BANK,PETTY_CASH',
        ]);

        $row = $this->service->createCashAccount($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'حساب نقدی ایجاد شد.',
            'data'    => $row,
        ], 201);
    }

    public function documents(Request $request): JsonResponse
    {
        $q = TreasuryDocument::query()->orderByDesc('document_date');
        if ($request->filled('company_id')) {
            $q->where('company_id', $request->string('company_id'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return response()->json(['status' => 'success', 'data' => $q->limit(200)->get()]);
    }

    public function storeDocument(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'                => 'required|uuid',
            'period_id'                 => 'required|uuid',
            'cash_account_id'           => 'required|uuid',
            'document_type'             => 'required|in:RECEIPT,PAYMENT',
            'document_date'             => 'required|date',
            'amount'                    => 'required|numeric|min:0.0001',
            'counterparty_name'         => 'nullable|string|max:200',
            'counterparty_open_item_id' => 'nullable|uuid',
            'description'               => 'nullable|string|max:500',
            'offset_account_id'         => 'required_if:auto_post,true|nullable|uuid',
            'ledger_id'                 => 'required_if:auto_post,true|nullable|uuid',
            'auto_post'                 => 'sometimes|boolean',
            'currency_id'               => 'nullable|uuid',
        ]);

        $row = $this->service->createDocument($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'سند خزانه ایجاد شد.',
            'data'    => $row,
        ], 201);
    }

    public function post(string $document, Request $request): JsonResponse
    {
        $data = $request->validate([
            'ledger_id'         => 'nullable|uuid',
            'offset_account_id' => 'required|uuid',
        ]);

        $row = $this->service->postToGl($document, $data);

        return response()->json([
            'status'  => 'success',
            'message' => 'سند خزانه در دفتر کل ثبت شد.',
            'data'    => $row,
        ]);
    }
}
