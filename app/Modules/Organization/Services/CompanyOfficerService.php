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
    ];

    /** Display order for list (Iranian corporate governance). */
    private const ROLE_SORT = [
        'CHAIRMAN'       => 10,
        'VICE_CHAIRMAN'  => 20,
        'BOARD_MEMBER'   => 30,
        'CEO'            => 40,
        'INSPECTOR'      => 50,
        'ALT_INSPECTOR'  => 60,
    ];

    public function create(array $data): CompanyOfficer
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $companyId = $data['company_id'];

        Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();

        $roleCode = $this->normalizeRoleCode($data['role_code'] ?? '');
        $ownershipId = $this->resolveOwnershipId($tenantId, $companyId, $data['ownership_id'] ?? null);

        return CompanyOfficer::create([
            'officer_id'            => (string) Str::uuid(),
            'tenant_id'             => $tenantId,
            'company_id'            => $companyId,
            'role_code'             => $roleCode,
            'role_title'            => $data['role_title'] ?? null,
            'full_name'             => trim((string) $data['full_name']),
            'person_user_id'        => $data['person_user_id'] ?? null,
            'ownership_id'          => $ownershipId,
            'national_id'           => $data['national_id'] ?? null,
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

        if (array_key_exists('role_code', $data)) {
            $row->role_code = $this->normalizeRoleCode($data['role_code']);
        }
        if (array_key_exists('role_title', $data)) {
            $row->role_title = $data['role_title'] ?: null;
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
            $row->national_id = $data['national_id'] ?: null;
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
                'نقش حقوقی نامعتبر است. فقط نقش‌های هیئت‌مدیره، مدیرعامل و بازرس مجاز هستند.'
            );
        }

        return $code;
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
