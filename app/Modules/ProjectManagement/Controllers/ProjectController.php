<?php

namespace App\Modules\ProjectManagement\Controllers;

use App\Base\Controller;
use App\Modules\ProjectManagement\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $service)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->list()]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->getById($id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_code' => ['required', 'string', 'max:50'],
            'name'         => ['required', 'string', 'max:200'],
            'description'  => ['nullable', 'string'],
            'start_date'   => ['required', 'date'],
            'end_date'     => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget'       => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json(['success' => true, 'data' => $this->service->create($data)], 201);
    }

    public function activate(string $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->activate($id)]);
    }

    public function complete(string $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->complete($id)]);
    }
}
