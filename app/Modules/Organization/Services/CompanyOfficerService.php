<?php

namespace App\Modules\Organization\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\CompanyOfficer;
use App\Modules\Organization\Models\CompanyOwnership;
use Illuminate\Support\Str;

/**
 * Legal officers only (هیئت‌مدیره، مدیرعامل، بازرس) — not operational roles.
 * Optional link to ownership (سهامدار) and optional person_user_id (tenant user).
 */
class CompanyOfficerService
{
    /** @var list<string> */
    public const LEGAL_ROLE_CODES = [
        'CHAIRMAN',
        'VICE_CHAIRMAN',
        'BOARD_MEMBER',
        'CEO',
        'INSPECTOR',
        'ALT_INSPECTOR',
        'OTHER',
    ];

    /** Roles that may exist only once (active) per company. */
    private const EXCLUSIVE_ROLES = [
        'CHAIRMAN',
        'VICE_CHAIRMAN',
        'CEO',
        'INSPECTOR',
        'ALT_INSPECTOR',
    ];

    /** Display order for list (Iranian corporate governance). */
    private const ROLE_SORT = [
        'CHAIRMAN'       => 10,
        'VICE_CHAIRMAN'  => 20,
        'BOARD_MEMBER'   => 30,
        'CEO'            => 40,
        'INSPECTOR'      => 50,
        'ALT_INSPECTOR'  => 60,
        'OTHER'          => 90,
    ];

    public function create(array $data): CompanyOfficer
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $companyId = $data['company_id'];

        Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();

        $roleCode = $this->normalizeRoleCode($data['role_code'] ?? '');
        $roleTitle = $this->resolveRoleTitle($roleCode, $data['role_title'] ?? null);
        $nationalId = $this->normalizeNationalId($data['national_id'] ?? null);

        $this->assertExclusiveRoleAvailable($tenantId, $companyId, $roleCode, null);
        $this->assertNationalIdUnique($tenantId, $companyId, $nationalId, null);

        $ownershipId = $this->resolveOwnershipId($tenantId, $companyId, $data['ownership_id'] ?? null);

        return CompanyOfficer::create([
            'officer_id'            => (string) Str::uuid(),
            'tenant_id'             => $tenantId,
            'company_id'            => $companyId,
            'role_code'             => $roleCode,
            'role_title'            => $roleTitle,
            'full_name'             => trim((string) $data['full_name']),
            'person_user_id'        => $data['person_user_id'] ?? null,
            'ownership_id'          => $ownershipId,
            'national_id'           => $nationalId,
            'mandate_from'          => $data['mandate_from'] ?? null,
            'mandate_to'            => $data['mandate_to'] ?? null,
            'has_signing_authority' => (bool) ($data['has_signing_authority'] ?? false),
            'mandate_notes'         => $data['mandate_notes'] ?? null,
            'is_active'             => (bool) ($data['is_active'] ?? true),
            'row_version'           => 1,
        ]);
    }

    public function update(string $officerId, array $data): CompanyOfficer
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = CompanyOfficer::where('tenant_id', $tenantId)
            ->where('officer_id', $officerId)
            ->firstOrFail();

        $roleCode = $row->role_code;
        if (array_key_exists('role_code', $data)) {
            $roleCode = $this->normalizeRoleCode($data['role_code']);
            $row->role_code = $roleCode;
        }

        if (array_key_exists('role_title', $data) || array_key_exists('role_code', $data)) {
            $titleRaw = array_key_exists('role_title', $data)
                ? $data['role_title']
                : $row->role_title;
            $row->role_title = $this->resolveRoleTitle($roleCode, $titleRaw);
        }

        if (array_key_exists('full_name', $data)) {
            $name = trim((string) $data['full_name']);
            if ($name === '') {
                throw new DomainException('نام مقام الزامی است.');
            }
            $row->full_name = $name;
        }
        if (array_key_exists('person_user_id', $data)) {
            $row->person_user_id = $data['person_user_id'] ?: null;
        }
        if (array_key_exists('ownership_id', $data)) {
            $row->ownership_id = $this->resolveOwnershipId(
                $tenantId,
                $row->company_id,
                $data['ownership_id']
            );
        }
        if (array_key_exists('national_id', $data)) {
            $nationalId = $this->normalizeNationalId($data['national_id']);
            $this->assertNationalIdUnique($tenantId, $row->company_id, $nationalId, $row->officer_id);
            $row->national_id = $nationalId;
        }
        if (array_key_exists('mandate_from', $data)) {
            $row->mandate_from = $data['mandate_from'] ?: null;
        }
        if (array_key_exists('mandate_to', $data)) {
            $row->mandate_to = $data['mandate_to'] ?: null;
        }
        if (array_key_exists('has_signing_authority', $data)) {
            $row->has_signing_authority = (bool) $data['has_signing_authority'];
        }
        if (array_key_exists('mandate_notes', $data)) {
            $row->mandate_notes = $data['mandate_notes'] ?: null;
        }
        if (array_key_exists('is_active', $data)) {
            $row->is_active = (bool) $data['is_active'];
        }

        $this->assertExclusiveRoleAvailable(
            $tenantId,
            $row->company_id,
            $roleCode,
            $row->officer_id
        );

        $row->row_version = (int) $row->row_version + 1;
        $row->save();

        return $row->fresh();
    }

    public function listForCompany(string $companyId)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();

        $rows = CompanyOfficer::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->orderBy('full_name')
            ->get();

        $ownershipIds = $rows->pluck('ownership_id')->filter()->unique()->values();
        $ownershipMap = [];
        if ($ownershipIds->isNotEmpty()) {
            $ownershipMap = CompanyOwnership::where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->whereIn('ownership_id', $ownershipIds)
                ->get()
                ->keyBy('ownership_id');
        }

        return $rows
            ->sortBy(function (CompanyOfficer $o) {
                $rank = self::ROLE_SORT[$o->role_code] ?? 90;

                return sprintf('%03d-%s', $rank, $o->full_name);
            })
            ->values()
            ->map(function (CompanyOfficer $o) use ($ownershipMap) {
                $arr = $o->toArray();
                $own = $o->ownership_id ? ($ownershipMap[$o->ownership_id] ?? null) : null;
                $arr['ownership'] = null;
                if ($own) {
                    $arr['ownership'] = [
                        'ownership_id'       => $own->ownership_id,
                        'owner_kind'         => $own->owner_kind,
                        'owner_display_name' => $own->owner_display_name,
                        'owner_company_id'   => $own->owner_company_id,
                        'ownership_percent'  => $own->ownership_percent,
                    ];
                }
                $arr['is_shareholder'] = $own !== null;

                return $arr;
            });
    }

    public function softDelete(string $officerId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = CompanyOfficer::where('tenant_id', $tenantId)
            ->where('officer_id', $officerId)
            ->firstOrFail();
        $row->delete();
    }

    private function normalizeRoleCode(string $raw): string
    {
        $code = strtoupper(trim($raw));
        if (! in_array($code, self::LEGAL_ROLE_CODES, true)) {
            throw new DomainException(
                'نقش حقوقی نامعتبر است. فقط نقش‌های هیئت‌مدیره، مدیرعامل، بازرس و سایر مجاز هستند.'
            );
        }

        return $code;
    }

    private function resolveRoleTitle(string $roleCode, mixed $title): ?string
    {
        $t = is_string($title) ? trim($title) : '';
        if ($roleCode === 'OTHER') {
            if ($t === '') {
                throw new DomainException('برای نقش «سایر» عنوان نقش الزامی است.');
            }

            return mb_substr($t, 0, 150);
        }

        return $t !== '' ? mb_substr($t, 0, 150) : null;
    }

    private function normalizeNationalId(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $digits = preg_replace('/\D+/u', '', (string) $raw) ?? '';
        // Persian/Arabic digits → ASCII already stripped by \D if converted upstream;
        // accept 10-digit national id when present.
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) > 20) {
            $digits = substr($digits, 0, 20);
        }

        return $digits;
    }

    private function assertExclusiveRoleAvailable(
        string $tenantId,
        string $companyId,
        string $roleCode,
        ?string $exceptOfficerId
    ): void {
        if (! in_array($roleCode, self::EXCLUSIVE_ROLES, true)) {
            return;
        }

        $q = CompanyOfficer::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('role_code', $roleCode)
            ->where('is_active', true);

        if ($exceptOfficerId) {
            $q->where('officer_id', '!=', $exceptOfficerId);
        }

        if ($q->exists()) {
            throw new DomainException(
                'این نقش حقوقی برای شرکت قبلاً ثبت شده است و نمی‌تواند تکراری باشد.'
            );
        }
    }

    private function assertNationalIdUnique(
        string $tenantId,
        string $companyId,
        ?string $nationalId,
        ?string $exceptOfficerId
    ): void {
        if ($nationalId === null || $nationalId === '') {
            return;
        }

        $q = CompanyOfficer::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('national_id', $nationalId);

        if ($exceptOfficerId) {
            $q->where('officer_id', '!=', $exceptOfficerId);
        }

        if ($q->exists()) {
            throw new DomainException('کد ملی تکراری است؛ این کد قبلاً برای مقام دیگری ثبت شده.');
        }
    }

    private function resolveOwnershipId(string $tenantId, string $companyId, mixed $ownershipId): ?string
    {
        if ($ownershipId === null || $ownershipId === '') {
            return null;
        }
        $id = (string) $ownershipId;
        $exists = CompanyOwnership::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('ownership_id', $id)
            ->exists();
        if (! $exists) {
            throw new DomainException('سهامدار انتخاب‌شده متعلق به این شرکت نیست.');
        }

        return $id;
    }
}
