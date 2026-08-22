<?php

namespace App\Modules\ProjectManagement\Controllers;

use App\Base\Controller;
use App\Modules\ProjectManagement\DTOs\ResourceAllocation\AllocateResourceDTO;
use App\Modules\ProjectManagement\Requests\ResourceAllocation\AllocateResourceRequest;
use App\Modules\ProjectManagement\Services\ResourceAllocationService;
use Illuminate\Http\JsonResponse;

class ResourceAllocationController extends Controller
{
    public function __construct(private readonly ResourceAllocationService $allocationService)
    {
    }

    public function store(AllocateResourceRequest $request): JsonResponse
    {
        $dto = AllocateResourceDTO::fromRequest($request);
        
        $allocation = $this->allocationService->allocate($dto);

        return response()->json([
            'message' => 'Resource allocated successfully and event published.',
            'data'    => $allocation
        ], 201);
    }
}