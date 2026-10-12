<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\ChequeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChequeController extends Controller
{
    public function __construct(
        private readonly ChequeService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $rows = $this->service->list([
            'company_id' => $request->filled('company_id') ? (string) $request->string('company_id') : null,
            'status'     => $request->filled('status') ? (string) $request->string('status') : null,
            'limit'      => (int) $request->input('limit', 200),
        ]);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'      => 'required|uuid',
            'direction'       => 'required|in:IN,OUT',
            'cheque_number'   => 'required|string|max:50',
            'due_date'        => 'required|date',
            'amount'          => 'required|numeric|min:0.0001',
            'bank_name'       => 'nullable|string|max:150',
            'issue_date'      => 'nullable|date',
            'payee_name'      => 'nullable|string|max:200',
            'drawer_name'     => 'nullable|string|max:200',
            'cash_account_id' => 'nullable|uuid',
            'description'     => 'nullable|string|max:500',
        ]);

        $row = $this->service->create($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'چک ثبت شد.',
            'data'    => $row,
        ], 201);
    }

    public function transition(string $cheque, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|string|in:RECEIVED,ISSUED,DEPOSITED,CLEARED,BOUNCED,CANCELLED',
        ]);

        $row = $this->service->transition($cheque, $data['status']);

        return response()->json([
            'status'  => 'success',
            'message' => 'وضعیت چک به‌روز شد.',
            'data'    => $row,
        ]);
    }
}
