
<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Requests\CreateAccountingRequest;
use App\Modules\Organization\DTOs\CreateAccountingDTO;
use App\Modules\Organization\Services\AccountingService;
use Illuminate\Http\JsonResponse;

class AccountingController extends Controller
{
    public function __construct(
        private readonly AccountingService $accountingService
    ) {
    }

    public function createFiscalPeriod(CreateAccountingRequest $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $dto = CreateAccountingDTO::fromRequest($request->validated());
        $companyId = $this->request()->route('company');

        $fiscalPeriod = $this->accountingService->createFiscalPeriod($dto, $companyId, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Fiscal period created successfully.',
            'data'    => $fiscalPeriod,
        ], 201);
    }

    public function createAccount(CreateAccountingRequest $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $dto = CreateAccountingDTO::fromRequest($request->validated());
        $companyId = $this->request()->route('company');

        $account = $this->accountingService->createAccount($dto, $companyId, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Account created successfully.',
            'data'    => $account,
        ], 201);
    }

    public function createVoucher(CreateAccountingRequest $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $dto = CreateAccountingDTO::fromRequest($request->validated());
        $companyId = $this->request()->route('company');

        $voucher = $this->accountingService->createVoucher($dto, $companyId, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Voucher created successfully.',
            'data'    => $voucher,
        ], 201);
    }

    public function createTaxTransaction(CreateAccountingRequest $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $dto = CreateAccountingDTO::fromRequest($request->validated());
        $companyId = $this->request()->route('company');

        $taxTransaction = $this->accountingService->createTaxTransaction($dto, $companyId, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tax transaction created successfully.',
            'data'    => $taxTransaction,
        ], 201);
    }

    private function getAuthenticatedUser()
    {
        return $this->request()->user();
    }

    private function getCurrentTenantId()
    {
        return $this->request()->headers->get('X-Tenant-ID');
    }
}