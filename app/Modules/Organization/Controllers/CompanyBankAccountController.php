<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\CompanyBankAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyBankAccountController extends Controller
{
    public function __construct(
        private readonly CompanyBankAccountService $service
    ) {
    }

    public function index(string $company): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listForCompany($company),
        ]);
    }

    public function store(string $company, Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_name'           => 'required|string|max:200',
            'account_number'      => 'required|string|max:100',
            'account_holder_name' => 'nullable|string|max:200',
            'label'               => 'nullable|string|max:200',
            'account_type'        => 'nullable|string|max:40',
            'iban'                => 'nullable|string|max:50',
            'swift_bic'           => 'nullable|string|max:20',
            'currency_id'         => 'nullable|uuid',
            'currency_code'       => 'nullable|string|max:10',
            'branch_name'         => 'nullable|string|max:150',
            'is_primary'          => 'sometimes|boolean',
            'is_active'           => 'sometimes|boolean',
            'notes'               => 'nullable|string|max:500',
        ]);

        $data['company_id'] = $company;
        $row = $this->service->create($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Bank account created.',
            'data'    => $row,
        ], 201);
    }

    public function update(string $bankAccount, Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_name'           => 'sometimes|required|string|max:200',
            'account_number'      => 'sometimes|required|string|max:100',
            'account_holder_name' => 'nullable|string|max:200',
            'label'               => 'nullable|string|max:200',
            'account_type'        => 'nullable|string|max:40',
            'iban'                => 'nullable|string|max:50',
            'swift_bic'           => 'nullable|string|max:20',
            'currency_id'         => 'nullable|uuid',
            'currency_code'       => 'nullable|string|max:10',
            'branch_name'         => 'nullable|string|max:150',
            'is_primary'          => 'sometimes|boolean',
            'is_active'           => 'sometimes|boolean',
            'notes'               => 'nullable|string|max:500',
        ]);

        $row = $this->service->update($bankAccount, $data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Bank account updated.',
            'data'    => $row,
        ]);
    }

    public function destroy(string $bankAccount): JsonResponse
    {
        $this->service->softDelete($bankAccount);

        return response()->json([
            'status'  => 'success',
            'message' => 'Bank account deleted.',
        ]);
    }
}
