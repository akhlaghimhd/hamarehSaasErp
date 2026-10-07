<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\FinanceComplianceAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceAlertController extends Controller
{
    public function __construct(
        private readonly FinanceComplianceAlertService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');
        $rows = $this->service->listOpen($companyId ? (string) $companyId : null);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
        ]);

        $n = $this->service->scanMissingMoodian($data['company_id']);

        return response()->json([
            'status'  => 'success',
            'message' => "{$n} هشدار جدید",
            'data'    => ['raised' => $n, 'open' => $this->service->listOpen($data['company_id'])],
        ]);
    }

    public function resolve(string $alert, Request $request): JsonResponse
    {
        $row = $this->service->resolve($alert, $request->user()?->id);

        return response()->json([
            'status'  => 'success',
            'message' => 'هشدار بسته شد.',
            'data'    => $row,
        ]);
    }
}
