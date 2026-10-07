<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\BankReconciliationService;
use App\Modules\FinancialAccounting\Infrastructure\Models\BankStatement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankReconciliationController extends Controller
{
    public function __construct(
        private readonly BankReconciliationService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $q = BankStatement::query()->with('lines')->orderByDesc('statement_date');
        if ($request->filled('company_id')) {
            $q->where('company_id', $request->string('company_id'));
        }

        return response()->json(['status' => 'success', 'data' => $q->limit(50)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'      => 'required|uuid',
            'cash_account_id' => 'required|uuid',
            'statement_date'  => 'required|date',
            'reference'       => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric',
            'closing_balance' => 'nullable|numeric',
            'lines'           => 'sometimes|array',
            'lines.*.line_date' => 'required_with:lines|date',
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.debit_amount' => 'nullable|numeric|min:0',
            'lines.*.credit_amount' => 'nullable|numeric|min:0',
        ]);

        $row = $this->service->createStatement($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'صورت‌حساب بانک ثبت شد.',
            'data'    => $row,
        ], 201);
    }

    public function matchLine(string $line, Request $request): JsonResponse
    {
        $data = $request->validate([
            'treasury_document_id' => 'nullable|uuid',
            'journal_entry_id'     => 'nullable|uuid',
        ]);

        $row = $this->service->matchLine(
            $line,
            $data['treasury_document_id'] ?? null,
            $data['journal_entry_id'] ?? null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'سطر تطبیق شد.',
            'data'    => $row,
        ]);
    }
}
