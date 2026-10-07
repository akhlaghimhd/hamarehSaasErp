<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\ConsolidatedTrialBalanceService;
use App\Modules\FinancialAccounting\Application\Services\IntercompanyJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntercompanyController extends Controller
{
    public function __construct(
        private readonly IntercompanyJournalService $ic,
        private readonly ConsolidatedTrialBalanceService $consolTb
    ) {
    }

    public function upsertMap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_company_id'     => 'required|uuid',
            'to_company_id'       => 'required|uuid',
            'due_from_account_id' => 'required|uuid',
            'due_to_account_id'   => 'required|uuid',
            'ic_partner_id'       => 'nullable|uuid',
            'description'         => 'nullable|string|max:500',
            'is_active'           => 'nullable|boolean',
        ]);

        $row = $this->ic->upsertAccountMap($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'نقشه حساب IC ذخیره شد.',
            'data'    => $row,
        ]);
    }

    public function createPair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_company_id'         => 'required|uuid',
            'to_company_id'           => 'required|uuid',
            'from_ledger_id'          => 'required|uuid',
            'to_ledger_id'            => 'required|uuid',
            'period_id'               => 'required|uuid',
            'amount'                  => 'required|numeric|min:0.0001',
            'from_offset_account_id'  => 'required|uuid',
            'to_offset_account_id'    => 'required|uuid',
            'document_date'           => 'nullable|date',
            'description'             => 'nullable|string|max:500',
            'ic_partner_id'           => 'nullable|uuid',
        ]);

        $result = $this->ic->createPairedDrafts($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'دو پیش‌نویس IC ساخته شد (ثبت قطعی نشده).',
            'data'    => $result,
        ], 201);
    }

    public function createElimination(Request $request): JsonResponse
    {
        $data = $request->validate([
            'elimination_company_id' => 'required|uuid',
            'ledger_id'              => 'required|uuid',
            'period_id'              => 'required|uuid',
            'due_from_account_id'    => 'required|uuid',
            'due_to_account_id'      => 'required|uuid',
            'amount'                 => 'required|numeric|min:0.0001',
            'document_date'          => 'nullable|date',
            'description'            => 'nullable|string|max:500',
        ]);

        $result = $this->ic->createEliminationDraft($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'پیش‌نویس حذف بین شرکتی ساخته شد.',
            'data'    => $result,
        ], 201);
    }

    public function consolidatedTrialBalance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'consolidation_company_id' => 'required|uuid',
            'period_id'                => 'required|uuid',
        ]);

        $rows = $this->consolTb->run(
            $data['consolidation_company_id'],
            $data['period_id']
        );

        return response()->json([
            'status' => 'success',
            'data'   => $rows,
        ]);
    }
}
