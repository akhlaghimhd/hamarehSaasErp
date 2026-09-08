<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    public function listForRecipient(string $tenantId, string $recipientUserId, bool $unreadOnly = false): Collection
    {
        $q = Notification::query()
            ->where('tenant_id', $tenantId)
            ->where('recipient_user_id', $recipientUserId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at');

        if ($unreadOnly) {
            $q->where('is_read', false);
        }

        return $q->get();
    }

    public function create(
        string $tenantId,
        string $recipientUserId,
        string $title,
        string $body,
        string $typeCode,
        ?string $createdBy = null
    ): Notification {
        return DB::transaction(function () use ($tenantId, $recipientUserId, $title, $body, $typeCode, $createdBy) {
            $notification = Notification::create([
                'tenant_id'         => $tenantId,
                'recipient_user_id' => $recipientUserId,
                'title'             => $title,
                'body'              => $body,
                'type_code'         => $typeCode,
                'is_read'           => false,
                'created_by'        => $createdBy,
                'updated_by'        => $createdBy,
            ]);

            $this->logEventOutbox(
                $tenantId,
                'notifications',
                $notification->notification_id,
                'SaasAdmin.NotificationCreated.v1',
                [
                    'notification_id'   => $notification->notification_id,
                    'recipient_user_id' => $recipientUserId,
                    'type_code'         => $typeCode,
                ]
            );

            return $notification;
        });
    }

    public function markRead(string $notificationId, ?string $updatedBy = null): Notification
    {
        return DB::transaction(function () use ($notificationId, $updatedBy) {
            $n = Notification::query()
                ->where('notification_id', $notificationId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $n->update([
                'is_read'     => true,
                'read_at'     => now(),
                'row_version' => ((int) ($n->row_version ?? 1)) + 1,
                'updated_by'  => $updatedBy,
            ]);

            return $n->fresh();
        });
    }

    public function softDelete(string $notificationId, ?string $deletedBy = null): void
    {
        DB::transaction(function () use ($notificationId, $deletedBy) {
            $n = Notification::query()
                ->where('notification_id', $notificationId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $n->deleted_by = $deletedBy;
            $n->save();
            $n->delete();
        });
    }

    private function logEventOutbox(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
