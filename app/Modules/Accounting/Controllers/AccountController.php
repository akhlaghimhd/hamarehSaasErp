<?php

namespace App\Modules\Accounting\Controllers;

use App\Base\Controller;
use App\Modules\Accounting\Requests\StoreAccountRequest;
use App\Modules\Accounting\Requests\UpdateAccountRequest;
use App\Modules\Accounting\DTOs\UpdateAccountDTO;
use App\Modules\Accounting\Services\AccountService;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accountService)
    {
    }

    public function index(): JsonResponse
    {
        $accounts = $this->accountService->getAll();

        return response()->json([
            'success' => true,
            'data'    => $accounts,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $account = $this->accountService->getById($id);

        return response()->json([
            'success' => true,
            'data'    => $account,
        ]);
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $dto = $request->toDTO();
        $account = $this->accountService->createAccount($dto);

        return response()->json([
            'success' => true,
            'message' => 'Chart of Account created successfully.',
            'data'    => [
                'account_id'   => $account->account_id,
                'code'         => $account->code,
                'name'         => $account->name,
                'level'        => $account->level,
                'account_type' => $account->account_type,
                'row_version'  => $account->row_version,
            ],
        ], 201);
    }

    public function update(UpdateAccountRequest $request, string $id): JsonResponse
    {
        $dto = UpdateAccountDTO::fromRequest($request);
        $account = $this->accountService->updateAccount($id, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully.',
            'data'    => $account,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->accountService->deleteAccount($id);

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.',
        ]);
    }
}
