<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Services\SupportTicketMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketMessageController extends Controller
{
    public function __construct(
        private readonly SupportTicketMessageService $messageService
    ) {
    }

    public function index(string $ticketId): JsonResponse
    {
        $list = $this->messageService->listByTicket($ticketId);

        return response()->json(['status' => 'success', 'data' => $list]);
    }

    public function store(Request $request, string $ticketId): JsonResponse
    {
        $validated = $request->validate([
            'sender_type'    => 'required|integer|in:1,2',
            'sender_user_id' => 'required|uuid',
            'message_body'   => 'required|string',
        ]);

        $msg = $this->messageService->addMessage(
            $ticketId,
            $validated['sender_type'],
            $validated['sender_user_id'],
            $validated['message_body']
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Message added.',
            'data'    => $msg,
        ], 201);
    }

    public function storeAttachment(Request $request, string $messageId): JsonResponse
    {
        $validated = $request->validate([
            'file_name'       => 'required|string|max:255',
            'storage_path'    => 'required|string|max:1000',
            'file_size_bytes' => 'required|integer|min:1',
        ]);

        $att = $this->messageService->addAttachment(
            $messageId,
            $validated['file_name'],
            $validated['storage_path'],
            $validated['file_size_bytes']
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Attachment added.',
            'data'    => $att,
        ], 201);
    }
}
