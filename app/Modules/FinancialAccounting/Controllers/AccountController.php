<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\ChartOfAccountsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(
        private readonly ChartOfAccountsService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tree = $request->boolean('tree', true);

        return response()->json([
            'status' => 'success',
            'data'   => $tree ? $this->service->listTree() : $this->service->listFlat(),
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
}
