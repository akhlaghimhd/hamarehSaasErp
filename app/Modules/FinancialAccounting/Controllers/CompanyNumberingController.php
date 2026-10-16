<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\CompanyNumberingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FIN-HOLD-W3 / ADR-FIN-003 L07 — company document numbering settings (branch series).
 */
class CompanyNumberingController extends Controller
{
    public function __construct(
        private readonly CompanyNumberingService $service
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
        ]);

        $row = $this->service->getForCompany($data['company_id']);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'company_id'                     => $row->company_id,
                'branch_series_enabled'          => (bool) $row->branch_series_enabled,
                'branch_series_from_fiscal_year' => $row->branch_series_from_fiscal_year,
                'row_version'                    => $row->row_version ?? 1,
            ],
        ]);
    }

    public function enableBranchSeries(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'                     => 'required|uuid',
            'branch_series_from_fiscal_year' => 'required|string|regex:/^\d{4}$/',
        ]);

        $row = $this->service->enableBranchSeries(
            $data['company_id'],
            $data['branch_series_from_fiscal_year'],
            auth()->id() ? (string) auth()->id() : null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'سری شماره‌گذاری شعبه از سال مالی مشخص فعال شد.',
            'data'    => [
                'company_id'                     => $row->company_id,
                'branch_series_enabled'          => (bool) $row->branch_series_enabled,
                'branch_series_from_fiscal_year' => $row->branch_series_from_fiscal_year,
                'row_version'                    => $row->row_version ?? 1,
            ],
        ]);
    }

    public function disableBranchSeries(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
        ]);

        $row = $this->service->disableBranchSeries(
            $data['company_id'],
            auth()->id() ? (string) auth()->id() : null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'سری شماره‌گذاری شعبه غیرفعال شد؛ اسناد بدون شعبه و با شعبه از سری شرکت استفاده می‌کنند.',
            'data'    => [
                'company_id'                     => $row->company_id,
                'branch_series_enabled'          => (bool) $row->branch_series_enabled,
                'branch_series_from_fiscal_year' => $row->branch_series_from_fiscal_year,
                'row_version'                    => $row->row_version ?? 1,
            ],
        ]);
    }
}
