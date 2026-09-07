<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Base\Controller;
use App\Modules\Manufacturing\Services\WorkCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkCenterController extends Controller
{
    public function __construct(
        private readonly WorkCenterService $service
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->list(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getById($id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'                   => ['required', 'string', 'max:50'],
            'name'                   => ['required', 'string', 'max:200'],
            'capacity_hours_per_day' => ['nullable', 'numeric', 'min:0'],
            'efficiency_percentage'  => ['nullable', 'numeric', 'min:0', 'max:200'],
            'cost_per_hour'          => ['nullable', 'numeric', 'min:0'],
            'status'                 => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $wc = $this->service->create($data);

        return response()->json([
            'success' => true,
            'data'    => $wc,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'code'                   => ['sometimes', 'string', 'max:50'],
            'name'                   => ['sometimes', 'string', 'max:200'],
            'capacity_hours_per_day' => ['sometimes', 'numeric', 'min:0'],
            'efficiency_percentage'  => ['sometimes', 'numeric', 'min:0', 'max:200'],
            'cost_per_hour'          => ['sometimes', 'numeric', 'min:0'],
            'status'                 => ['sometimes', 'integer', 'in:1,2,3'],
        ]);

        $wc = $this->service->update($id, $data);

        return response()->json([
            'success' => true,
            'data'    => $wc,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true]);
    }
}
