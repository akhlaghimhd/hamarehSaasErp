<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Services\SodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Exception;

class SodController extends Controller
{
    public function __construct(
        private readonly SodService $sodService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $rules = $this->sodService->listRules([
            'status' => $request->query('status'),
            'only_trashed' => $request->boolean('only_trashed'),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'فهرست قوانین تفکیک وظایف دریافت شد.',
            'data'    => $rules,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'role_a_id'   => 'required|uuid',
                'role_b_id'   => 'required|uuid',
                'code'        => 'nullable|string|max:100',
                'name'        => 'required|string|max:200',
                'description' => 'nullable|string|max:500',
                'severity'    => 'nullable|integer|min:1|max:4',
                'enforcement' => 'nullable|string|in:BLOCK,WARN,block,warn',
                'is_active'   => 'nullable|boolean',
            ]);

            $rule = $this->sodService->createRule($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'قانون تفکیک وظایف ثبت شد.',
                'data'    => $rule->load(['roleA:tenant_role_id,code,name', 'roleB:tenant_role_id,code,name']),
            ], 201);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name'           => 'nullable|string|max:200',
                'description'    => 'nullable|string|max:500',
                'severity'       => 'nullable|integer|min:1|max:4',
                'enforcement'    => 'nullable|string|in:BLOCK,WARN,block,warn',
                'is_active'      => 'nullable|boolean',
                'inactive_until' => 'nullable|date',
            ]);

            $rule = $this->sodService->updateRule($id, $validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'قانون به‌روزرسانی شد.',
                'data'    => $rule,
            ], 200);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->sodService->softDeleteRule($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'قانون به‌صورت نرم حذف شد. می‌توانید از سطل بازیابی آن را بازگردانید.',
            ], 200);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function restore(string $id): JsonResponse
    {
        try {
            $rule = $this->sodService->restoreRule($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'قانون با موفقیت بازگردانی شد.',
                'data'    => $rule,
            ], 200);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function evaluate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'role_ids'   => 'required|array|min:1',
                'role_ids.*' => 'uuid',
            ]);

            $tenantId = app()->bound('current_tenant_id') ? (string) app('current_tenant_id') : '';
            $result = $this->sodService->evaluateRoleSet($tenantId, $validated['role_ids']);

            return response()->json([
                'status'  => 'success',
                'message' => $result['has_block']
                    ? 'تعارض مسدودکننده یافت شد.'
                    : ($result['has_warn'] ? 'هشدار تفکیک وظایف وجود دارد.' : 'تعارضی یافت نشد.'),
                'data'    => $result,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
