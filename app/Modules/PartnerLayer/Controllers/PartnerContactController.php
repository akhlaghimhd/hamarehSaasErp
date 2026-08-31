<?php

namespace App\Modules\PartnerLayer\Controllers;

use App\Base\Controller;
use App\Modules\PartnerLayer\Requests\CreatePartnerContactRequest;
use App\Modules\PartnerLayer\Requests\UpdatePartnerContactRequest;
use App\Modules\PartnerLayer\DTOs\CreatePartnerContactDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerContactDTO;
use App\Modules\PartnerLayer\Services\PartnerContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PartnerContactController extends Controller
{
    public function __construct(private readonly PartnerContactService $service) {}

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->query('partner_id');
        $items = $this->service->getContacts($partnerId ? (string) $partnerId : null);

        return response()->json(['status' => 'success', 'data' => $items]);
    }

    public function store(CreatePartnerContactRequest $request): JsonResponse
    {
        try {
            $item = $this->service->createContact(
                CreatePartnerContactDTO::fromRequest($request->validated())
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Contact created successfully.',
                'data' => $item,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function show(string $contact): JsonResponse
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => $this->service->getContactById($contact),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Contact not found or access denied.'], 404);
        }
    }

    public function update(string $contact, UpdatePartnerContactRequest $request): JsonResponse
    {
        try {
            $item = $this->service->updateContact(
                $contact,
                UpdatePartnerContactDTO::fromRequest($request->validated())
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Contact updated successfully.',
                'data' => $item,
            ]);
        } catch (Exception $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $status);
        }
    }

    public function destroy(string $contact): JsonResponse
    {
        try {
            $this->service->deleteContact($contact);

            return response()->json(['status' => 'success', 'message' => 'Contact deleted successfully.']);
        } catch (Exception $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $status);
        }
    }
}
