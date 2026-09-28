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

    public function restoreChannel(string $channel): JsonResponse
    {
        $row = $this->service->restoreChannel($channel);

        return response()->json(['status' => 'success', 'message' => 'Channel restored.', 'data' => $row]);
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

    public function restoreDivision(string $division): JsonResponse
    {
        $row = $this->service->restoreDivision($division);

        return response()->json(['status' => 'success', 'message' => 'Division restored.', 'data' => $row]);
    }

    public function salesAreas(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listSalesAreas($request->query('membership') === 'deleted'),
        ]);
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

    public function updateSalesArea(string $salesArea, Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'      => 'nullable|string|max:80',
            'name'      => 'nullable|string|max:200',
            'is_active' => 'sometimes|boolean',
        ]);

        $row = $this->service->updateSalesArea(
            $salesArea,
            array_key_exists('code', $data) ? $data['code'] : null,
            array_key_exists('name', $data) ? $data['name'] : null,
            array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null
        );

        return response()->json(['status' => 'success', 'message' => 'Sales area updated.', 'data' => $row]);
    }

    public function destroySalesArea(string $salesArea): JsonResponse
    {
        $this->service->softDeleteSalesArea($salesArea);

        return response()->json(['status' => 'success', 'message' => 'Sales area deleted.']);
    }

    public function restoreSalesArea(string $salesArea): JsonResponse
    {
        $row = $this->service->restoreSalesArea($salesArea);

        return response()->json(['status' => 'success', 'message' => 'Sales area restored.', 'data' => $row]);
    }

    public function offices(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listOffices($request->query('membership') === 'deleted'),
        ]);
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

    public function updateOffice(string $office, Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'         => 'required|string|max:50',
            'name'         => 'required|string|max:200',
            'sales_org_id' => 'nullable|uuid',
            'is_active'    => 'sometimes|boolean',
        ]);

        $row = $this->service->updateOffice(
            $office,
            $data['code'],
            $data['name'],
            $data['sales_org_id'] ?? null,
            array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null
        );

        return response()->json(['status' => 'success', 'message' => 'Sales office updated.', 'data' => $row]);
    }

    public function destroyOffice(string $office): JsonResponse
    {
        $this->service->softDeleteOffice($office);

        return response()->json(['status' => 'success', 'message' => 'Sales office deleted.']);
    }

    public function restoreOffice(string $office): JsonResponse
    {
        $row = $this->service->restoreOffice($office);

        return response()->json(['status' => 'success', 'message' => 'Sales office restored.', 'data' => $row]);
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

    public function updateGroup(string $group, Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'      => 'required|string|max:50',
            'name'      => 'required|string|max:200',
            'is_active' => 'sometimes|boolean',
        ]);

        $row = $this->service->updateGroup(
            $group,
            $data['code'],
            $data['name'],
            array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null
        );

        return response()->json(['status' => 'success', 'message' => 'Sales group updated.', 'data' => $row]);
    }

    public function destroyGroup(string $group): JsonResponse
    {
        $this->service->softDeleteGroup($group);

        return response()->json(['status' => 'success', 'message' => 'Sales group deleted.']);
    }

    public function restoreGroup(string $group): JsonResponse
    {
        $row = $this->service->restoreGroup($group);

        return response()->json(['status' => 'success', 'message' => 'Sales group restored.', 'data' => $row]);
    }
}
