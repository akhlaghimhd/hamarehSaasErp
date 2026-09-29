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

    public function index(): JsonResponse
    {
        $rules = $this->sodService->listRules();

        return response()->json([
            'status'  => 'success',
            'message' => 'SoD rules retrieved successfully.',
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
                'message' => 'SoD rule created successfully.',
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

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->sodService->softDeleteRule($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'SoD rule soft-deleted successfully.',
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

    /**
     * Dry-run: evaluate a proposed role set without assigning.
     */
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
                    : ($result['has_warn'] ? 'هشدار SoD وجود دارد.' : 'تعارضی یافت نشد.'),
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
