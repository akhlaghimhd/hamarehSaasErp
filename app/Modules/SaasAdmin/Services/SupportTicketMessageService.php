<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\SupportTicket;
use App\Modules\SaasAdmin\Models\SupportTicketAttachment;
use App\Modules\SaasAdmin\Models\SupportTicketMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;

class SupportTicketMessageService
{
    public function listByTicket(string $ticketId): Collection
    {
        return SupportTicketMessage::query()
            ->where('ticket_id', $ticketId)
            ->with('attachments')
            ->orderBy('created_at')
            ->get();
    }

    public function addMessage(
        string $ticketId,
        int $senderType,
        string $senderUserId,
        string $messageBody
    ): SupportTicketMessage {
        return DB::transaction(function () use ($ticketId, $senderType, $senderUserId, $messageBody) {
            SupportTicket::query()
                ->where('ticket_id', $ticketId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            return SupportTicketMessage::create([
                'message_id'     => (string) Str::uuid(),
                'ticket_id'      => $ticketId,
                'sender_type'    => $senderType,
                'sender_user_id' => $senderUserId,
                'message_body'   => $messageBody,
                'created_at'     => now(),
            ]);
        });
    }

    public function addAttachment(
        string $messageId,
        string $fileName,
        string $storagePath,
        int $fileSizeBytes
    ): SupportTicketAttachment {
        SupportTicketMessage::query()
            ->where('message_id', $messageId)
            ->firstOrFail();

        return SupportTicketAttachment::create([
            'attachment_id'   => (string) Str::uuid(),
            'message_id'      => $messageId,
            'file_name'       => $fileName,
            'storage_path'    => $storagePath,
            'file_size_bytes' => $fileSizeBytes,
            'created_at'      => now(),
        ]);
    }
}
