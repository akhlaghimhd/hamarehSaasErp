<?php

namespace App\Modules\IdentityCore\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ID-W3-04 — Read-only audit package for compliance / SIEM export.
 *
 * Returns structured snapshots; caller can serialize to JSON/CSV.
 * Does not mutate data. Tenant-scoped only.
 */
class IdentityAuditExportService
{
    public const SECTION_MEMBERSHIP = 'membership_history';
    public const SECTION_USER_ROLES = 'user_roles';
    public const SECTION_PRIVILEGED = 'privileged_grants';
    public const SECTION_ROLE_REQUESTS = 'role_assignment_requests';
    public const SECTION_ACCESS_CERT = 'access_certifications';

    /**
     * @param  list<string>|null  $sections  null = all available sections
     * @return array{tenant_id:string,exported_at:string,sections:array<string,mixed>}
     */
    public function export(
        string $tenantId,
        ?array $sections = null,
        ?string $from = null,
        ?string $to = null,
        int $limitPerSection = 500
    ): array {
        if ($tenantId === '') {
            throw new HttpException(400, 'tenant_id الزامی است.');
        }

        $limit = max(1, min($limitPerSection, 2000));
        $all = [
            self::SECTION_MEMBERSHIP,
            self::SECTION_USER_ROLES,
            self::SECTION_PRIVILEGED,
            self::SECTION_ROLE_REQUESTS,
            self::SECTION_ACCESS_CERT,
        ];

        $wanted = $sections === null || $sections === []
            ? $all
            : array_values(array_intersect($all, array_map('strval', $sections)));

        if ($wanted === []) {
            throw new HttpException(422, 'هیچ بخش معتبری برای export انتخاب نشده است.');
        }

        $fromAt = $from ? \Carbon\Carbon::parse($from) : null;
        $toAt = $to ? \Carbon\Carbon::parse($to) : null;

        $payload = [];
        foreach ($wanted as $section) {
            $payload[$section] = match ($section) {
                self::SECTION_MEMBERSHIP => $this->membershipHistory($tenantId, $fromAt, $toAt, $limit),
                self::SECTION_USER_ROLES => $this->userRoles($tenantId, $limit),
                self::SECTION_PRIVILEGED => $this->privilegedGrants($tenantId, $fromAt, $toAt, $limit),
                self::SECTION_ROLE_REQUESTS => $this->roleRequests($tenantId, $fromAt, $toAt, $limit),
                self::SECTION_ACCESS_CERT => $this->accessCertifications($tenantId, $fromAt, $toAt, $limit),
                default => [],
            };
        }

        return [
            'tenant_id'   => $tenantId,
            'exported_at' => now()->toIso8601String(),
            'filters'     => [
                'from'     => $fromAt?->toIso8601String(),
                'to'       => $toAt?->toIso8601String(),
                'sections' => $wanted,
                'limit'    => $limit,
            ],
            'sections'    => $payload,
        ];
    }

    private function membershipHistory(string $tenantId, $from, $to, int $limit): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('tenant_membership_histories')) {
            return [];
        }

        $q = DB::table('tenant_membership_histories')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderByDesc('effective_date')
            ->limit($limit);

        if ($from) {
            $q->where('effective_date', '>=', $from);
        }
        if ($to) {
            $q->where('effective_date', '<=', $to);
        }

        return $q->get([
            'history_id',
            'tenant_user_id',
            'previous_status',
            'new_status',
            'reason_code',
            'description',
            'effective_date',
            'created_by',
            'created_at',
        ])->map(fn ($r) => (array) $r)->all();
    }

    private function userRoles(string $tenantId, int $limit): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('tenant_user_roles')) {
            return [];
        }

        return DB::table('tenant_user_roles as ur')
            ->leftJoin('tenant_roles as r', 'ur.tenant_role_id', '=', 'r.tenant_role_id')
            ->where('ur.tenant_id', $tenantId)
            ->whereNull('ur.deleted_at')
            ->orderBy('ur.user_id')
            ->limit($limit)
            ->get([
                'ur.tenant_user_role_id',
                'ur.user_id',
                'ur.tenant_role_id',
                'r.code as role_code',
                'r.name as role_name',
                'ur.valid_from',
                'ur.valid_to',
                'ur.created_at',
            ])->map(fn ($r) => (array) $r)->all();
    }

    private function privilegedGrants(string $tenantId, $from, $to, int $limit): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('tenant_privileged_grants')) {
            return [];
        }

        $q = DB::table('tenant_privileged_grants')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($from) {
            $q->where('created_at', '>=', $from);
        }
        if ($to) {
            $q->where('created_at', '<=', $to);
        }

        return $q->get()->map(fn ($r) => (array) $r)->all();
    }

    private function roleRequests(string $tenantId, $from, $to, int $limit): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('tenant_role_assignment_requests')) {
            return [];
        }

        $q = DB::table('tenant_role_assignment_requests')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($from) {
            $q->where('created_at', '>=', $from);
        }
        if ($to) {
            $q->where('created_at', '<=', $to);
        }

        return $q->get([
            'request_id',
            'user_id',
            'tenant_role_id',
            'status',
            'valid_from',
            'valid_to',
            'reason',
            'requested_by',
            'reviewed_by',
            'reviewed_at',
            'created_at',
        ])->map(fn ($r) => (array) $r)->all();
    }

    private function accessCertifications(string $tenantId, $from, $to, int $limit): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('tenant_access_certification_campaigns')) {
            return [];
        }

        $q = DB::table('tenant_access_certification_campaigns')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($from) {
            $q->where('created_at', '>=', $from);
        }
        if ($to) {
            $q->where('created_at', '<=', $to);
        }

        return $q->get()->map(fn ($r) => (array) $r)->all();
    }
}
