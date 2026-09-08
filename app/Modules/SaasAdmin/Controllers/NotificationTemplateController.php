<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Services\NotificationTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function __construct(
        private readonly NotificationTemplateService $templateService
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->templateService->list(),
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_code' => 'required|string|max:100',
            'title'         => 'required|string|max:200',
            'body_template' => 'required|string',
            'channel'       => 'required|string|max:50',
            'is_active'     => 'nullable|boolean',
        ]);

        $t = $this->templateService->upsert(
            $validated['template_code'],
            $validated['title'],
            $validated['body_template'],
            $validated['channel'],
            $validated['is_active'] ?? true
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Notification template saved.',
            'data'    => $t,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->templateService->softDelete($id);

        return response()->json(['status' => 'success', 'message' => 'Template deleted.']);
    }
}
