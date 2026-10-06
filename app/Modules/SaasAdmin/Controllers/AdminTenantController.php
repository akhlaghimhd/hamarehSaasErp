<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Services\AdminTenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AdminTenantController extends Controller
{
    public function __construct(
        private readonly AdminTenantService $tenants
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:200',
            'status'   => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $page = $this->tenants->list(
            $validated['search'] ?? null,
            array_key_exists('status', $validated) ? (int) $validated['status'] : null,
            (int) ($validated['per_page'] ?? 20)
        );

        return response()->json([
            'status' => 'success',
            'data'   => $page->items(),
            'meta'   => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function show(string $tenantId): JsonResponse
    {
        try {
            $detail = $this->tenants->show($tenantId);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status'  => 'error',
                'message' => 'مستأجر یافت نشد.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $detail,
        ]);
    }
}
