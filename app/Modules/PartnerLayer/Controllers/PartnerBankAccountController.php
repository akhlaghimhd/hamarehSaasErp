<?php

namespace App\Modules\PartnerLayer\Controllers;

use App\Base\Controller;
use App\Modules\PartnerLayer\Requests\CreatePartnerBankAccountRequest;
use App\Modules\PartnerLayer\Requests\UpdatePartnerBankAccountRequest;
use App\Modules\PartnerLayer\DTOs\CreatePartnerBankAccountDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerBankAccountDTO;
use App\Modules\PartnerLayer\Services\PartnerBankAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PartnerBankAccountController extends Controller
{
    public function __construct(private readonly PartnerBankAccountService $service) {}

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->query('partner_id');
        $items = $this->service->getAccounts($partnerId ? (string) $partnerId : null);

        return response()->json(['status' => 'success', 'data' => $items]);
    }

    public function store(CreatePartnerBankAccountRequest $request): JsonResponse
    {
        try {
            $item = $this->service->createAccount(
                CreatePartnerBankAccountDTO::fromRequest($request->validated())
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Bank account created successfully.',
                'data' => $item,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function show(string $bankAccount): JsonResponse
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => $this->service->getAccountById($bankAccount),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Bank account not found or access denied.'], 404);
        }
    }

    public function update(string $bankAccount, UpdatePartnerBankAccountRequest $request): JsonResponse
    {
        try {
            $item = $this->service->updateAccount(
                $bankAccount,
                UpdatePartnerBankAccountDTO::fromRequest($request->validated())
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Bank account updated successfully.',
                'data' => $item,
            ]);
        } catch (Exception $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $status);
        }
    }

    public function destroy(string $bankAccount): JsonResponse
    {
        try {
            $this->service->deleteAccount($bankAccount);

            return response()->json(['status' => 'success', 'message' => 'Bank account deleted successfully.']);
        } catch (Exception $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $status);
        }
    }
}
