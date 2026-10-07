<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\FixedAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixedAssetController extends Controller
{
    public function __construct(
        private readonly FixedAssetService $assets
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $this->assets->list($data['company_id']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'              => 'required|uuid',
            'asset_code'              => 'required|string|max:40',
            'name'                    => 'required|string|max:200',
            'asset_account_id'        => 'required|uuid',
            'accum_depr_account_id'   => 'required|uuid',
            'depr_expense_account_id' => 'required|uuid',
            'cost_center_id'          => 'nullable|uuid',
            'acquisition_date'        => 'required|date',
            'acquisition_cost'        => 'required|numeric|min:0.0001',
            'salvage_value'           => 'nullable|numeric|min:0',
            'useful_life_months'      => 'required|integer|min:1',
            'depreciation_method'     => 'nullable|string|max:20',
        ]);

        $row = $this->assets->create($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'دارایی ثابت ثبت شد.',
            'data'    => $row,
        ], 201);
    }

    public function runDepreciation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'period_id'  => 'required|uuid',
            'ledger_id'  => 'required|uuid',
        ]);

        $result = $this->assets->runDepreciation(
            $data['company_id'],
            $data['period_id'],
            $data['ledger_id'],
            $request->user()?->id
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'استهلاک اجرا شد؛ سند پیش‌نویس ساخته شد (ثبت قطعی نشده).',
            'data'    => $result,
        ]);
    }
}
