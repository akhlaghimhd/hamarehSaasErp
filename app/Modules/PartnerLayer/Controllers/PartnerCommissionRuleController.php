<?php

namespace App\Modules\PartnerLayer\Controllers;

use App\Base\Controller;
use App\Modules\PartnerLayer\Requests\CreatePartnerCommissionRuleRequest;
use App\Modules\PartnerLayer\Requests\UpdatePartnerCommissionRuleRequest;
use App\Modules\PartnerLayer\DTOs\CreatePartnerCommissionRuleDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerCommissionRuleDTO;
use App\Modules\PartnerLayer\Services\PartnerCommissionRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PartnerCommissionRuleController extends Controller
{
    public function __construct(
        private readonly PartnerCommissionRuleService $ruleService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $agreementId = $request->query('agreement_id');

        $items = $this->ruleService->getRules(
            $agreementId ? (string) $agreementId : null
        );

        return response()->json([
            'status' => 'success',
            'data'   => $items,
        ]);
    }

    public function store(CreatePartnerCommissionRuleRequest $request): JsonResponse
    {
        try {
            $dto = CreatePartnerCommissionRuleDTO::fromRequest($request->validated());
            $item = $this->ruleService->createRule($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Commission rule created successfully.',
                'data'    => $item,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $commissionRule): JsonResponse
    {
        try {
            $item = $this->ruleService->getRuleById($commissionRule);

            return response()->json([
                'status' => 'success',
                'data'   => $item,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Commission rule not found or access denied.',
            ], 404);
        }
    }

    public function update(string $commissionRule, UpdatePartnerCommissionRuleRequest $request): JsonResponse
    {
        try {
            $dto = UpdatePartnerCommissionRuleDTO::fromRequest($request->validated());
            $item = $this->ruleService->updateRule($commissionRule, $dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Commission rule updated successfully.',
                'data'    => $item,
            ]);
        } catch (Exception $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function destroy(string $commissionRule): JsonResponse
    {
        try {
            $this->ruleService->deleteRule($commissionRule);

            return response()->json([
                'status'  => 'success',
                'message' => 'Commission rule deleted successfully.',
            ]);
        } catch (Exception $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
