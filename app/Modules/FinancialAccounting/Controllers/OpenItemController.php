<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\OpenItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenItemController extends Controller
{
    public function __construct(
        private readonly OpenItemService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'side'       => 'nullable|in:AR,AP',
        ]);

        $rows = $this->service->listOpen($data['company_id'], $data['side'] ?? null);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'          => 'required|uuid',
            'side'                => 'required|in:AR,AP',
            'document_date'       => 'required|date',
            'due_date'            => 'nullable|date',
            'counterparty_name'   => 'required|string|max:200',
            'original_amount'     => 'required|numeric|min:0.0001',
            'document_number'     => 'nullable|string|max:100',
            'document_type'       => 'nullable|string|max:40',
            'gl_account_id'       => 'nullable|uuid',
            'description'         => 'nullable|string|max:500',
        ]);

        $row = $this->service->registerManualInvoice($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'فاکتور دستی ثبت شد.',
            'data'    => $row,
        ], 201);
    }

    public function allocate(string $openItem, Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount'               => 'required|numeric|min:0.0001',
            'treasury_document_id' => 'nullable|uuid',
            'journal_entry_id'     => 'nullable|uuid',
            'allocation_date'      => 'nullable|date',
            'description'          => 'nullable|string|max:500',
        ]);

        $row = $this->service->allocate($openItem, (float) $data['amount'], $data);

        return response()->json([
            'status'  => 'success',
            'message' => 'تخصیص انجام شد.',
            'data'    => $row,
        ]);
    }

    public function aging(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'side'       => 'required|in:AR,AP',
            'as_of'      => 'nullable|date',
        ]);

        $rows = $this->service->aging(
            $data['company_id'],
            $data['side'],
            $data['as_of'] ?? null
        );

        return response()->json(['status' => 'success', 'data' => $rows]);
    }
}
