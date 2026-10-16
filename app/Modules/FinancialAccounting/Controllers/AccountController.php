<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Application\Services\ChartOfAccountsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-HOLD-W4: CoA per company.
 * list/create prefer explicit company_id; if omitted, fall back to primary company
 * so legacy FE (accounts-page locked) keeps working on single-company path.
 */
class AccountController extends Controller
{
    public function __construct(
        private readonly ChartOfAccountsService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        if ($companyId === null) {
            return response()->json([
                'status'  => 'error',
                'message' => 'شناسه شرکت الزامی است.',
                'code'    => 'fin.coa.company_required',
            ], 422);
        }

        $tree = $request->boolean('tree', true);

        return response()->json([
            'status' => 'success',
            'data'   => $tree
                ? $this->service->listTree($companyId)
                : $this->service->listFlat($companyId),
        ]);
    }

    public function show(string $account): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->find($account),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'         => 'nullable|uuid',
            'account_code'       => 'required|string|max:50',
            'name'               => 'required|string|max:200',
            'account_type'       => 'required|integer|in:1,2,3,4,5',
            'normal_balance'     => 'sometimes|integer|in:1,2',
            'parent_account_id'  => 'nullable|uuid',
            'is_control_account' => 'sometimes|boolean',
            'is_postable'        => 'sometimes|boolean',
            'account_level'      => 'sometimes|integer|min:1',
            'status'             => 'sometimes|integer',
        ]);

        if (empty($data['company_id'])) {
            $fallback = $this->resolvePrimaryCompanyId();
            if ($fallback === null) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'شناسه شرکت الزامی است.',
                    'code'    => 'fin.coa.company_required',
                ], 422);
            }
            $data['company_id'] = $fallback;
        }

        $row = $this->service->create($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'حساب ایجاد شد.',
            'data'    => $row,
        ], 201);
    }

    public function update(string $account, Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_code'       => 'sometimes|string|max:50',
            'name'               => 'sometimes|string|max:200',
            'account_type'       => 'sometimes|integer|in:1,2,3,4,5',
            'normal_balance'     => 'sometimes|integer|in:1,2',
            'parent_account_id'  => 'nullable|uuid',
            'is_control_account' => 'sometimes|boolean',
            'is_postable'        => 'sometimes|boolean',
            'status'             => 'sometimes|integer',
        ]);

        $row = $this->service->update($account, $data);

        return response()->json([
            'status'  => 'success',
            'message' => 'حساب به‌روز شد.',
            'data'    => $row,
        ]);
    }

    public function destroy(string $account): JsonResponse
    {
        $this->service->softDelete($account);

        return response()->json([
            'status'  => 'success',
            'message' => 'حساب حذف شد.',
        ]);
    }

    protected function resolveCompanyId(Request $request): ?string
    {
        $fromQuery = trim((string) $request->query('company_id', ''));
        if ($fromQuery !== '') {
            return $fromQuery;
        }

        return $this->resolvePrimaryCompanyId();
    }

    protected function resolvePrimaryCompanyId(): ?string
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (! $tenantId || ! Schema::hasTable('erp_companies')) {
            return null;
        }

        $id = DB::table('erp_companies')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderByRaw('CASE WHEN is_primary IS TRUE THEN 0 ELSE 1 END')
            ->orderBy('created_at')
            ->value('company_id');

        return $id ? (string) $id : null;
    }
}
