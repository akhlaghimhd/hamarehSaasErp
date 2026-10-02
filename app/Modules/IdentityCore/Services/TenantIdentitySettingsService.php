<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\SaasPlatform\Models\TenantSetting;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Tenant-owned identity policy settings (not SaaS feature packs).
 * Customer toggles dual-control for role grant/revoke from system settings.
 */
class TenantIdentitySettingsService
{
    public const KEY_REQUIRE_ROLE_ASSIGNMENT_APPROVAL = 'require_role_assignment_approval';

    public const GROUP_IDENTITY = 'IDENTITY';

    public function requireRoleAssignmentApproval(?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?: $this->currentTenantId();
        if ($tenantId === '') {
            return false;
        }

        $row = TenantSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('setting_key', self::KEY_REQUIRE_ROLE_ASSIGNMENT_APPROVAL)
            ->whereNull('deleted_at')
            ->first();

        if (!$row || $row->setting_value === null || $row->setting_value === '') {
            return false; // default OFF — current direct path
        }

        $v = strtolower(trim((string) $row->setting_value));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array{require_role_assignment_approval: bool}
     */
    public function getIdentitySettings(?string $tenantId = null): array
    {
        return [
            'require_role_assignment_approval' => $this->requireRoleAssignmentApproval($tenantId),
        ];
    }

    /**
     * @param  array{require_role_assignment_approval?: bool|string|int}  $payload
     * @return array{require_role_assignment_approval: bool}
     */
    public function updateIdentitySettings(array $payload, ?string $tenantId = null, ?string $actorId = null): array
    {
        $tenantId = $tenantId ?: $this->currentTenantId();
        if ($tenantId === '') {
            throw new \RuntimeException('بافت مستأجر مشخص نیست.');
        }

        if (array_key_exists('require_role_assignment_approval', $payload)) {
            $on = filter_var($payload['require_role_assignment_approval'], FILTER_VALIDATE_BOOLEAN);
            $this->upsert(
                $tenantId,
                self::KEY_REQUIRE_ROLE_ASSIGNMENT_APPROVAL,
                $on ? 'true' : 'false',
                self::GROUP_IDENTITY,
                $actorId
            );
        }

        return $this->getIdentitySettings($tenantId);
    }

    protected function upsert(
        string $tenantId,
        string $key,
        string $value,
        string $group,
        ?string $actorId
    ): void {
        $row = TenantSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('setting_key', $key)
            ->whereNull('deleted_at')
            ->first();

        if ($row) {
            $row->update([
                'setting_value' => $value,
                'setting_group' => $group,
                'updated_by'    => $actorId,
            ]);

            return;
        }

        TenantSetting::create([
            'tenant_setting_id' => (string) Str::uuid(),
            'tenant_id'         => $tenantId,
            'setting_key'       => $key,
            'setting_value'     => $value,
            'setting_group'     => $group,
            'created_by'        => $actorId,
            'updated_by'        => $actorId,
            'row_version'       => 1,
        ]);
    }

    protected function currentTenantId(): string
    {
        if (app()->bound('current_tenant_id') && app('current_tenant_id')) {
            return (string) app('current_tenant_id');
        }

        $ctx = Context::get('security_context');
        if (is_array($ctx) && !empty($ctx['tenant_id'])) {
            return (string) $ctx['tenant_id'];
        }

        return (string) (\App\Base\Context\TenantContext::getInstance()->getTenantId() ?? '');
    }
}
