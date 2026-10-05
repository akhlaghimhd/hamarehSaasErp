<?php

namespace App\Modules\Organization\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\CostCenter;
use Illuminate\Support\Str;

/**
 * Organization SoT for cost centers (master only).
 * Accounting consumers are debt — see ORG_Cost_Centers_Model_v1.0.md §6.
 */
class CostCenterService
{
    /** @var list<string> */
    public const TYPES = [
        'ADMIN',
        'SALES',
        'PRODUCTION',
        'SUPPORT',
        'R_AND_D',
        'SHARED',
        'OTHER',
    ];

    public function create(array $data): CostCenter
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $companyId = $data['company_id'];

        Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();

        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === '' || $name === '') {
            throw new DomainException('کد و نام مرکز هزینه الزامی است.');
        }

        $this->assertCodeUnique($tenantId, $companyId, $code, null);

        $parentId = $this->resolveParentId($tenantId, $companyId, $data['parent_cost_center_id'] ?? null, null);
        $type = $this->normalizeType($data['cost_center_type'] ?? 'ADMIN');
        [$from, $to] = $this->normalizeValidity($data['valid_from'] ?? null, $data['valid_to'] ?? null);

        return CostCenter::create([
            'cost_center_id'        => (string) Str::uuid(),
            'tenant_id'             => $tenantId,
            'company_id'            => $companyId,
            'department_id'         => $data['department_id'] ?? null,
            'parent_cost_center_id' => $parentId,
            'code'                  => $code,
            'name'                  => $name,
            'cost_center_type'      => $type,
            'manager_user_id'       => $data['manager_user_id'] ?? null,
            'description'           => isset($data['description']) ? (trim((string) $data['description']) ?: null) : null,
            'valid_from'            => $from,
            'valid_to'              => $to,
            'is_active'             => (bool) ($data['is_active'] ?? true),
            'row_version'           => 1,
        ]);
    }

    public function update(string $costCenterId, array $data): CostCenter
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = CostCenter::where('tenant_id', $tenantId)
            ->where('cost_center_id', $costCenterId)
            ->firstOrFail();

        if (array_key_exists('code', $data)) {
            $code = trim((string) $data['code']);
            if ($code === '') {
                throw new DomainException('کد مرکز هزینه الزامی است.');
            }
            $this->assertCodeUnique($tenantId, $row->company_id, $code, $row->cost_center_id);
            $row->code = $code;
        }
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new DomainException('نام مرکز هزینه الزامی است.');
            }
            $row->name = $name;
        }
        if (array_key_exists('cost_center_type', $data)) {
            $row->cost_center_type = $this->normalizeType($data['cost_center_type']);
        }
        if (array_key_exists('department_id', $data)) {
            $row->department_id = $data['department_id'] ?: null;
        }
        if (array_key_exists('parent_cost_center_id', $data)) {
            $row->parent_cost_center_id = $this->resolveParentId(
                $tenantId,
                $row->company_id,
                $data['parent_cost_center_id'],
                $row->cost_center_id
            );
        }
        if (array_key_exists('manager_user_id', $data)) {
            $row->manager_user_id = $data['manager_user_id'] ?: null;
        }
        if (array_key_exists('description', $data)) {
            $row->description = $data['description'] !== null && $data['description'] !== ''
                ? mb_substr(trim((string) $data['description']), 0, 500)
                : null;
        }
        if (array_key_exists('valid_from', $data) || array_key_exists('valid_to', $data)) {
            $from = array_key_exists('valid_from', $data) ? $data['valid_from'] : $row->valid_from;
            $to = array_key_exists('valid_to', $data) ? $data['valid_to'] : $row->valid_to;
            [$fromN, $toN] = $this->normalizeValidity($from, $to);
            $row->valid_from = $fromN;
            $row->valid_to = $toN;
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

        return CostCenter::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();
    }

    public function softDelete(string $costCenterId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = CostCenter::where('tenant_id', $tenantId)
            ->where('cost_center_id', $costCenterId)
            ->firstOrFail();

        $hasChildren = CostCenter::where('tenant_id', $tenantId)
            ->where('parent_cost_center_id', $costCenterId)
            ->exists();
        if ($hasChildren) {
            throw new DomainException('ابتدا مراکز هزینه فرزند را حذف یا جابه‌جا کنید.');
        }

        $row->delete();
    }

    private function normalizeType(mixed $raw): string
    {
        $t = strtoupper(trim((string) $raw));
        if (! in_array($t, self::TYPES, true)) {
            throw new DomainException('نوع مرکز هزینه نامعتبر است.');
        }

        return $t;
    }

    /** @return array{0:?string,1:?string} */
    private function normalizeValidity(mixed $from, mixed $to): array
    {
        $f = $from ? substr((string) $from, 0, 10) : null;
        $t = $to ? substr((string) $to, 0, 10) : null;
        if ($f === '') {
            $f = null;
        }
        if ($t === '') {
            $t = null;
        }
        if ($f && $t && $t < $f) {
            throw new DomainException('تاریخ پایان اعتبار نمی‌تواند قبل از شروع باشد.');
        }

        return [$f, $t];
    }

    private function assertCodeUnique(
        string $tenantId,
        string $companyId,
        string $code,
        ?string $exceptId
    ): void {
        $q = CostCenter::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('code', $code);
        if ($exceptId) {
            $q->where('cost_center_id', '!=', $exceptId);
        }
        if ($q->exists()) {
            throw new DomainException('کد مرکز هزینه در این شرکت تکراری است.');
        }
    }

    private function resolveParentId(
        string $tenantId,
        string $companyId,
        mixed $parentId,
        ?string $selfId
    ): ?string {
        if ($parentId === null || $parentId === '') {
            return null;
        }
        $pid = (string) $parentId;
        if ($selfId && $pid === $selfId) {
            throw new DomainException('مرکز هزینه نمی‌تواند والد خودش باشد.');
        }

        $parent = CostCenter::where('tenant_id', $tenantId)
            ->where('cost_center_id', $pid)
            ->first();
        if (! $parent || $parent->company_id !== $companyId) {
            throw new DomainException('مرکز هزینه والد نامعتبر است.');
        }

        if ($selfId) {
            $cursor = $parent;
            $guard = 0;
            while ($cursor && $guard < 50) {
                if ($cursor->cost_center_id === $selfId) {
                    throw new DomainException('سلسله‌مراتب مرکز هزینه دچار حلقه می‌شود.');
                }
                if (! $cursor->parent_cost_center_id) {
                    break;
                }
                $cursor = CostCenter::where('tenant_id', $tenantId)
                    ->where('cost_center_id', $cursor->parent_cost_center_id)
                    ->first();
                $guard++;
            }
        }

        return $pid;
    }
}
