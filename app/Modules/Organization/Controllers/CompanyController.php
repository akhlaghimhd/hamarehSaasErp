<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller; // کنترلر بیس پروژه شما
use App\Modules\Organization\Requests\CreateCompanyRequest;
use App\Modules\Organization\Services\CompanyService;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $companyService)
    {
    }

    public function index(): JsonResponse
    {
        $companies = $this->companyService->getAllCompanies();
        return response()->json(['data' => $companies]);
    }

    public function store(CreateCompanyRequest $request): JsonResponse
    {
        try {
            $company = $this->companyService->createCompany($request->toDTO());
            
            return response()->json([
                'message' => 'شرکت با موفقیت ثبت شد.',
                'data' => $company
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
    public function update(\App\Modules\Organization\Requests\UpdateCompanyRequest $request, string $id): JsonResponse
    {
        try {
            $company = $this->companyService->updateCompany($id, $request->toDTO());
            
            return response()->json([
                'message' => 'اطلاعات شرکت با موفقیت ویرایش شد.',
                'data' => $company
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->companyService->deleteCompany($id);
            return response()->json(['message' => 'شرکت با موفقیت حذف شد.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}