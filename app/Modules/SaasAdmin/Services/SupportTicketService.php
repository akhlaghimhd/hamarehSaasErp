<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\SupportTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class SupportTicketService
{
    public function listByTenant(string $tenantId): Collection
    {
        return SupportTicket::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function get(string $ticketId): SupportTicket
    {
        return SupportTicket::query()
            ->where('ticket_id', $ticketId)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    public function create(
        string $tenantId,
        string $subject,
        ?string $description = null,
        ?string $tenantUserId = null,
        int $priority = 2,
        string $channel = 'PORTAL',
        ?string $createdBy = null
    ): SupportTicket {
        return DB::transaction(function () use ($tenantId, $subject, $description, $tenantUserId, $priority, $channel, $createdBy) {
            $ticketNumber = 'TKT-' . strtoupper(Str::random(10));

            $ticket = SupportTicket::create([
                'tenant_id'      => $tenantId,
                'tenant_user_id' => $tenantUserId,
                'ticket_number'  => $ticketNumber,
                'subject'        => $subject,
                'description'    => $description,
                'priority'       => $priority,
                'status'         => 1,
                'channel'        => $channel,
                'created_by'     => $createdBy,
                'updated_by'     => $createdBy,
            ]);

            $this->logEventOutbox(
                $tenantId,
                'support_tickets',
                $ticket->ticket_id,
                'SaasAdmin.SupportTicketCreated.v1',
                [
                    'ticket_id'     => $ticket->ticket_id,
                    'ticket_number' => $ticketNumber,
                    'subject'       => $subject,
                ]
            );

            return $ticket;
        });
    }

    public function update(
        string $ticketId,
        ?string $subject = null,
        ?string $description = null,
        ?int $priority = null,
        ?int $status = null,
        ?string $assignedAdminUserId = null,
        ?string $updatedBy = null
    ): SupportTicket {
        return DB::transaction(function () use ($ticketId, $subject, $description, $priority, $status, $assignedAdminUserId, $updatedBy) {
            $ticket = $this->get($ticketId);

            $changes = array_filter([
                'subject'                 => $subject,
                'description'             => $description,
                'priority'                => $priority,
                'status'                  => $status,
                'assigned_admin_user_id'  => $assignedAdminUserId,
            ], fn ($v) => !is_null($v));

            if ($status === 3) { // closed
                $changes['closed_at'] = now();
            }
            if ($status === 4) { // resolved
                $changes['resolved_at'] = now();
            }

            if (!empty($changes)) {
                $changes['row_version'] = ((int) ($ticket->row_version ?? 1)) + 1;
                $changes['updated_by']  = $updatedBy;
                $ticket->update($changes);
            }

            $this->logEventOutbox(
                $ticket->tenant_id,
                'support_tickets',
                $ticket->ticket_id,
                'SaasAdmin.SupportTicketUpdated.v1',
                [
                    'ticket_id' => $ticket->ticket_id,
                    'changes'   => $changes,
                ]
            );

            return $ticket->fresh();
        });
    }

    public function softDelete(string $ticketId, ?string $deletedBy = null): void
    {
        DB::transaction(function () use ($ticketId, $deletedBy) {
            $ticket = $this->get($ticketId);
            $ticket->deleted_by = $deletedBy;
            $ticket->save();
            $ticket->delete();

            $this->logEventOutbox(
                $ticket->tenant_id,
                'support_tickets',
                $ticketId,
                'SaasAdmin.SupportTicketDeleted.v1',
                ['ticket_id' => $ticketId]
            );
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
