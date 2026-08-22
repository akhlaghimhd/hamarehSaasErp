<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\MasterData\Services\BankAccountService;
use App\Modules\MasterData\Requests\CreateBankAccountRequest;
use App\Modules\MasterData\Requests\UpdateBankAccountRequest;
use App\Modules\MasterData\DTOs\CreateBankAccountDTO;
use App\Modules\MasterData\DTOs\UpdateBankAccountDTO;

class BankAccountController extends Controller
{
    public function __construct(private readonly BankAccountService $bankAccountService)
    {
    }

    public function index(): JsonResponse
    {
        $accounts = $this->bankAccountService->getAll();
        return response()->json(['data' => $accounts]);
    }

    public function show(string $id): JsonResponse
    {
        $account = $this->bankAccountService->getById($id);
        return response()->json(['data' => $account]);
    }

    public function store(CreateBankAccountRequest $request): JsonResponse
    {
        $dto = CreateBankAccountDTO::fromRequest($request);
        $account = $this->bankAccountService->create($dto);
        
        return response()->json(['data' => $account, 'message' => 'Bank Account created successfully.'], 201);
    }

    public function update(UpdateBankAccountRequest $request, string $id): JsonResponse
    {
        $dto = UpdateBankAccountDTO::fromRequest($request);
        $account = $this->bankAccountService->update($id, $dto);
        
        return response()->json(['data' => $account, 'message' => 'Bank Account updated successfully.']);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->bankAccountService->delete($id);
        return response()->json(['message' => 'Bank Account deleted successfully.']);
    }
}