<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantSodRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ID-W1-01 / ID-W1-02 — Segregation of Duties.
 */
class SodService
{
    /**
     * Evaluate proposed role set for a user against active SoD rules.
     *
     * @param  array<string>  $proposedRoleIds
     * @return array{conflicts: list<array>, has_block: bool, has_warn: bool}
     */
    public function evaluateRoleSet(string $tenantId, array $proposedRoleIds): array
    {
        $roleIds = array_values(array_unique(array_filter($proposedRoleIds)));
        if (count($roleIds) < 2) {
            return ['conflicts' => [], 'has_block' => false, 'has_warn' => false];
        }

        $rules = TenantSodRule::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get();

        $set = array_flip($roleIds);
        $conflicts = [];
        $hasBlock = false;
        $hasWarn = false;

        foreach ($rules as $rule) {
            $a = (string) $rule->role_a_id;
            $b = (string) $rule->role_b_id;
            if (isset($set[$a]) && isset($set[$b])) {
                $conflicts[] = [
                    'sod_rule_id' => $rule->sod_rule_id,
                    'code'        => $rule->code,
                    'name'        => $rule->name,
                    'severity'    => (int) $rule->severity,
                    'enforcement' => $rule->enforcement,
                    'role_a_id'   => $a,
                    'role_b_id'   => $b,
                ];
                if ($rule->enforcement === TenantSodRule::ENFORCEMENT_BLOCK) {
                    $hasBlock = true;
                } else {
                    $hasWarn = true;
                }
            }
        }

        return [
            'conflicts' => $conflicts,
            'has_block' => $hasBlock,
            'has_warn'  => $hasWarn,
        ];
    }

    /**
     * Assert proposed role set is allowed. Throws 422 on BLOCK conflicts.
     *
     * @param  array<string>  $proposedRoleIds
     * @return list<array>  WARN conflicts (empty if none)
     */
    public function assertAssignable(string $tenantId, array $proposedRoleIds): array
    {
        $result = $this->evaluateRoleSet($tenantId, $proposedRoleIds);

        if ($result['has_block']) {
            $names = collect($result['conflicts'])
                ->filter(fn ($c) => $c['enforcement'] === TenantSodRule::ENFORCEMENT_BLOCK)
                ->pluck('name')
                ->implode('؛ ');

            throw new HttpException(
                422,
                'تخصیص نقش‌ها با قوانین تفکیک وظایف (SoD) در تعارض است: ' . $names
            );
        }

        return $result['conflicts'];
    }

    public function listRules(): Collection
    {
        $tenantId = $this->tenantId();

        return TenantSodRule::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'roleA:tenant_role_id,code,name',
                'roleB:tenant_role_id,code,name',
            ])
            ->orderByDesc('severity')
            ->orderBy('code')
            ->get();
    }

    public function createRule(array $data): TenantSodRule
    {
        $tenantId = $this->tenantId();
        $roleA = (string) $data['role_a_id'];
        $roleB = (string) $data['role_b_id'];

        if ($roleA === $roleB) {
            throw new HttpException(422, 'دو نقش یک قانون SoD نمی‌توانند یکسان باشند.');
        }

        // Canonical order to keep unique pair stable
        if (strcmp($roleA, $roleB) > 0) {
            [$roleA, $roleB] = [$roleB, $roleA];
        }

        TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $roleA)
            ->firstOrFail();
        TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $roleB)
            ->firstOrFail();

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = 'sod-' . Str::lower(Str::random(8));
        }

        $enforcement = strtoupper((string) ($data['enforcement'] ?? TenantSodRule::ENFORCEMENT_BLOCK));
        if (!in_array($enforcement, [TenantSodRule::ENFORCEMENT_BLOCK, TenantSodRule::ENFORCEMENT_WARN], true)) {
            throw new HttpException(422, 'enforcement باید BLOCK یا WARN باشد.');
        }

        $severity = (int) ($data['severity'] ?? TenantSodRule::SEVERITY_HIGH);
        if ($severity < 1 || $severity > 4) {
            throw new HttpException(422, 'severity باید بین ۱ تا ۴ باشد.');
        }

        return DB::transaction(function () use ($tenantId, $roleA, $roleB, $code, $data, $enforcement, $severity) {
            $exists = TenantSodRule::query()
                ->where('tenant_id', $tenantId)
                ->where('role_a_id', $roleA)
                ->where('role_b_id', $roleB)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                throw new HttpException(422, 'قانون SoD برای این جفت نقش از قبل وجود دارد.');
            }

            return TenantSodRule::create([
                'sod_rule_id' => (string) Str::uuid(),
                'tenant_id'   => $tenantId,
                'role_a_id'   => $roleA,
                'role_b_id'   => $roleB,
                'code'        => $code,
                'name'        => trim((string) $data['name']),
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
                'severity'    => $severity,
                'enforcement' => $enforcement,
                'is_active'   => (bool) ($data['is_active'] ?? true),
            ]);
        });
    }

    public function softDeleteRule(string $sodRuleId): void
    {
        $tenantId = $this->tenantId();

        $rule = TenantSodRule::query()
            ->where('tenant_id', $tenantId)
            ->where('sod_rule_id', $sodRuleId)
            ->firstOrFail();

        $rule->delete();
    }

    private function tenantId(): string
    {
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
        if (!$tenantId) {
            throw new HttpException(400, 'Tenant context is required.');
        }

        return (string) $tenantId;
    }
}
