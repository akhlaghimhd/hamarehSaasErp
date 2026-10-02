<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantRoleAssignmentRequest;
use App\Modules\IdentityCore\Models\TenantUserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ID-W3-02 — Request / approve / reject role assignments.
 * Supports GRANT and REVOKE so existing access stays active until revoke is approved.
 */
class RoleAssignmentApprovalService
{
    public function __construct(
        private readonly RoleAssignmentValidityService $validity,
        private readonly SodService $sod,
    ) {
    }

    public function listPending(string $tenantId): Collection
    {
        return TenantRoleAssignmentRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('status', TenantRoleAssignmentRequest::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->get();
    }

    public function requestAssignment(
        string $tenantId,
        string $userId,
        string $roleId,
        string $requestedBy,
        ?string $reason = null,
        ?string $validFrom = null,
        ?string $validTo = null,
        string $requestAction = TenantRoleAssignmentRequest::ACTION_GRANT
    ): TenantRoleAssignmentRequest {
        $requestAction = strtoupper($requestAction);
        if (!in_array($requestAction, [
            TenantRoleAssignmentRequest::ACTION_GRANT,
            TenantRoleAssignmentRequest::ACTION_REVOKE,
        ], true)) {
            throw new HttpException(422, 'نوع درخواست نامعتبر است.');
        }

        $role = TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $roleId)
            ->whereNull('deleted_at')
            ->first();

        if (!$role) {
            throw new HttpException(404, 'نقش یافت نشد.');
        }

        $this->validity->assertValidWindow($validFrom, $validTo);

        if ($requestAction === TenantRoleAssignmentRequest::ACTION_GRANT) {
            $existing = $this->validity->effectiveRoleIds($tenantId, $userId);
            $this->sod->assertAssignable($tenantId, array_values(array_unique(array_merge($existing, [$roleId]))));
        }

        $pendingExists = TenantRoleAssignmentRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('tenant_role_id', $roleId)
            ->where('status', TenantRoleAssignmentRequest::STATUS_PENDING)
            ->exists();

        if ($pendingExists) {
            throw new HttpException(422, 'درخواست در انتظار تأیید برای این نقش از قبل وجود دارد.');
        }

        return DB::transaction(function () use (
            $tenantId, $userId, $roleId, $requestedBy, $reason, $validFrom, $validTo, $requestAction
        ) {
            $req = TenantRoleAssignmentRequest::create([
                'request_id'     => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'user_id'        => $userId,
                'tenant_role_id' => $roleId,
                'request_action' => $requestAction,
                'status'         => TenantRoleAssignmentRequest::STATUS_PENDING,
                'valid_from'     => $validFrom,
                'valid_to'       => $validTo,
                'reason'         => $reason,
                'requested_by'   => $requestedBy,
                'created_by'     => $requestedBy,
                'row_version'    => 1,
            ]);

            $this->outbox($tenantId, $req->request_id, 'identity.role_assignment.requested.v1', [
                'request_id'     => $req->request_id,
                'user_id'        => $userId,
                'tenant_role_id' => $roleId,
                'request_action' => $requestAction,
            ]);

            return $req;
        });
    }

    public function approve(
        string $tenantId,
        string $requestId,
        string $reviewedBy,
        ?string $reviewNote = null
    ): TenantRoleAssignmentRequest {
        return DB::transaction(function () use ($tenantId, $requestId, $reviewedBy, $reviewNote) {
            $req = TenantRoleAssignmentRequest::query()
                ->where('tenant_id', $tenantId)
                ->where('request_id', $requestId)
                ->lockForUpdate()
                ->first();

            if (!$req) {
                throw new HttpException(404, 'درخواست یافت نشد.');
            }

            if ($req->status !== TenantRoleAssignmentRequest::STATUS_PENDING) {
                throw new HttpException(422, 'فقط درخواست‌های در انتظار قابل تأیید هستند.');
            }

            if ($req->requested_by === $reviewedBy) {
                throw new HttpException(422, 'درخواست‌دهنده نمی‌تواند خودش درخواست را تأیید کند.');
            }

            $action = strtoupper((string) ($req->request_action ?? TenantRoleAssignmentRequest::ACTION_GRANT));

            if ($action === TenantRoleAssignmentRequest::ACTION_REVOKE) {
                DB::table('tenant_user_roles')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $req->user_id)
                    ->where('tenant_role_id', $req->tenant_role_id)
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => now(),
                        'deleted_by' => $reviewedBy,
                        'updated_at' => now(),
                    ]);
            } else {
                $existing = $this->validity->effectiveRoleIds($tenantId, $req->user_id);
                $this->sod->assertAssignable(
                    $tenantId,
                    array_values(array_unique(array_merge($existing, [$req->tenant_role_id])))
                );

                $already = DB::table('tenant_user_roles')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $req->user_id)
                    ->where('tenant_role_id', $req->tenant_role_id)
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$already) {
                    DB::table('tenant_user_roles')->insert([
                        'tenant_user_role_id' => (string) Str::uuid(),
                        'tenant_id'           => $tenantId,
                        'user_id'             => $req->user_id,
                        'tenant_role_id'      => $req->tenant_role_id,
                        'valid_from'          => $req->valid_from,
                        'valid_to'            => $req->valid_to,
                        'created_by'          => $reviewedBy,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                        'row_version'         => 1,
                    ]);
                }
            }

            $req->update([
                'status'      => TenantRoleAssignmentRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
                'row_version' => ((int) $req->row_version) + 1,
            ]);

            app(IdentitySessionReevaluationService::class)
                ->invalidateUser($tenantId, $req->user_id, 'role_assignment_approved');

            $this->outbox($tenantId, $req->request_id, 'identity.role_assignment.approved.v1', [
                'request_id'     => $req->request_id,
                'user_id'        => $req->user_id,
                'tenant_role_id' => $req->tenant_role_id,
                'request_action' => $action,
            ]);

            return $req->fresh();
        });
    }

    public function reject(
        string $tenantId,
        string $requestId,
        string $reviewedBy,
        ?string $reviewNote = null
    ): TenantRoleAssignmentRequest {
        return DB::transaction(function () use ($tenantId, $requestId, $reviewedBy, $reviewNote) {
            $req = TenantRoleAssignmentRequest::query()
                ->where('tenant_id', $tenantId)
                ->where('request_id', $requestId)
                ->lockForUpdate()
                ->first();

            if (!$req) {
                throw new HttpException(404, 'درخواست یافت نشد.');
            }

            if ($req->status !== TenantRoleAssignmentRequest::STATUS_PENDING) {
                throw new HttpException(422, 'فقط درخواست‌های در انتظار قابل رد هستند.');
            }

            $req->update([
                'status'      => TenantRoleAssignmentRequest::STATUS_REJECTED,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
                'row_version' => ((int) $req->row_version) + 1,
            ]);

            $this->outbox($tenantId, $req->request_id, 'identity.role_assignment.rejected.v1', [
                'request_id' => $req->request_id,
            ]);

            return $req->fresh();
        });
    }

    private function outbox(string $tenantId, string $aggregateId, string $eventType, array $payload): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('event_outbox')) {
            return;
        }

        DB::table('event_outbox')->insert([
            'event_id'       => (string) Str::uuid(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => 'tenant_role_assignment_requests',
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
