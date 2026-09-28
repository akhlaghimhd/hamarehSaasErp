<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\SalesStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesStructureController extends Controller
{
    public function __construct(
        private readonly SalesStructureService $service
    ) {
    }

    public function channels(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listChannels($request->query('membership') === 'deleted'),
        ]);
    }

    public function storeChannel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'      => 'required|string|max:50',
            'name'      => 'required|string|max:200',
            'is_active' => 'sometimes|boolean',
        ]);

        $row = $this->service->createChannel($data['code'], $data['name'], (bool) ($data['is_active'] ?? true));

        return response()->json(['status' => 'success', 'message' => 'Channel created.', 'data' => $row], 201);
    }

    public function updateChannel(string $channel, Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'      => 'required|string|max:50',
            'name'      => 'required|string|max:200',
            'is_active' => 'sometimes|boolean',
        ]);

        $row = $this->service->updateChannel(
            $channel,
            $data['code'],
            $data['name'],
            array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null
        );

        return response()->json(['status' => 'success', 'message' => 'Channel updated.', 'data' => $row]);
    }

    public function destroyChannel(string $channel): JsonResponse
    {
        $this->service->softDeleteChannel($channel);

        return response()->json(['status' => 'success', 'message' => 'Channel deleted.']);
    }

    public function divisions(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listDivisions($request->query('membership') === 'deleted'),
        ]);
    }

    public function storeDivision(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'      => 'required|string|max:50',
            'name'      => 'required|string|max:200',
            'is_active' => 'sometimes|boolean',
        ]);

        $row = $this->service->createDivision($data['code'], $data['name'], (bool) ($data['is_active'] ?? true));

        return response()->json(['status' => 'success', 'message' => 'Division created.', 'data' => $row], 201);
    }

    public function updateDivision(string $division, Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'      => 'required|string|max:50',
            'name'      => 'required|string|max:200',
            'is_active' => 'sometimes|boolean',
        ]);

        $row = $this->service->updateDivision(
            $division,
            $data['code'],
            $data['name'],
            array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null
        );

        return response()->json(['status' => 'success', 'message' => 'Division updated.', 'data' => $row]);
    }

    public function destroyDivision(string $division): JsonResponse
    {
        $this->service->softDeleteDivision($division);

        return response()->json(['status' => 'success', 'message' => 'Division deleted.']);
    }

    public function salesAreas(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->service->listSalesAreas()]);
    }

    public function storeSalesArea(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sales_org_id'            => 'required|uuid',
            'distribution_channel_id' => 'required|uuid',
            'division_id'             => 'required|uuid',
            'code'                    => 'nullable|string|max:80',
            'name'                    => 'nullable|string|max:200',
            'is_active'               => 'sometimes|boolean',
        ]);

        $row = $this->service->createSalesArea(
            $data['sales_org_id'],
            $data['distribution_channel_id'],
            $data['division_id'],
            $data['code'] ?? null,
            $data['name'] ?? null,
            (bool) ($data['is_active'] ?? true)
        );

        return response()->json(['status' => 'success', 'message' => 'Sales area created.', 'data' => $row], 201);
    }

    public function destroySalesArea(string $salesArea): JsonResponse
    {
        $this->service->softDeleteSalesArea($salesArea);

        return response()->json(['status' => 'success', 'message' => 'Sales area deleted.']);
    }

    public function offices(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->service->listOffices()]);
    }

    public function storeOffice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'         => 'required|string|max:50',
            'name'         => 'required|string|max:200',
            'sales_org_id' => 'nullable|uuid',
            'is_active'    => 'sometimes|boolean',
        ]);

        $row = $this->service->createOffice(
            $data['code'],
            $data['name'],
            $data['sales_org_id'] ?? null,
            (bool) ($data['is_active'] ?? true)
        );

        return response()->json(['status' => 'success', 'message' => 'Sales office created.', 'data' => $row], 201);
    }

    public function destroyOffice(string $office): JsonResponse
    {
        $this->service->softDeleteOffice($office);

        return response()->json(['status' => 'success', 'message' => 'Sales office deleted.']);
    }

    public function storeGroup(string $office, Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'      => 'required|string|max:50',
            'name'      => 'required|string|max:200',
            'is_active' => 'sometimes|boolean',
        ]);

        $row = $this->service->createGroup($office, $data['code'], $data['name'], (bool) ($data['is_active'] ?? true));

        return response()->json(['status' => 'success', 'message' => 'Sales group created.', 'data' => $row], 201);
    }

    public function destroyGroup(string $group): JsonResponse
    {
        $this->service->softDeleteGroup($group);

        return response()->json(['status' => 'success', 'message' => 'Sales group deleted.']);
    }
}
