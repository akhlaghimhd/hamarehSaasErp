<?php

namespace App\Modules\IdentityCore\Catalog;

/**
 * IDORG-T05 / G05 + T08 / G17 — Access layers & role templates (logical).
 *
 * Layers (product law ADR-ID-ORG-004 / ADR-FIN-003):
 * - PLATFORM_ADMIN  : technical owner / tenant security admin pack (not FIN operate-all)
 * - GROUP_READ      : group observer — list/report across granted companies; no mutate
 * - COMPANY_OPERATE : write/manage within actor COMPANY scopes only
 * - BRANCH          : reserved for Wave 3 (IDORG-T15); not enforced yet
 *
 * Physical role table stays tenant-wide; ceilings are logical templates + HoldingAccess guards.
 * Permission tagging is by code convention + this catalog (no DB column required for Wave 1).
 */
final class AccessLayerCatalog
{
    public const LAYER_PLATFORM_ADMIN = 'platform_admin';

    public const LAYER_GROUP_READ = 'group_read';

    public const LAYER_COMPANY_OPERATE = 'company_operate';

    public const LAYER_BRANCH = 'branch';

    /** Explicit group-observer capability (IDORG-T09). */
    public const PERM_GROUP_OBSERVE = 'identity.group.observe';

    /** Role template codes (DefaultRoleCatalog / seed). */
    public const ROLE_GROUP_OBSERVER = 'group-observer';

    public const ROLE_COMPANY_OPERATOR = 'company-operator';

    public const ROLE_COMPANY_USER = 'company-user';

    public const ROLE_PLATFORM_ADMIN = 'platform-admin';

    /**
     * Curated pack for is_owner login bootstrap (IDORG-T07 / G16).
     * Tenant technical admin only — not every operational permission in the catalog.
     *
     * @return list<string>
     */
    public static function platformAdminPermissionCodes(): array
    {
        return [
            'identity.user.view', 'identity.user.create', 'identity.user.update', 'identity.user.delete',
            'identity.role.view', 'identity.role.create', 'identity.role.update', 'identity.role.delete',
            'identity.role.assign', 'identity.role.approve', 'identity.role.assign-permissions',
            'identity.permission.view',
            'identity.scope.view', 'identity.scope.create', 'identity.scope.update', 'identity.scope.delete', 'identity.scope.assign',
            'identity.profile.view', 'identity.profile.update',
            'identity.membership_history.view',
            'identity.sod.view', 'identity.sod.manage',
            'identity.system_notification.receive',
            'identity.mfa.manage',
            'identity.access_cert.view', 'identity.access_cert.manage', 'identity.access_cert.certify', 'identity.access_cert.receive_reminder',
            'identity.privileged.view', 'identity.privileged.request', 'identity.privileged.approve',
            self::PERM_GROUP_OBSERVE,
            'organization.company.view', 'organization.branch.view', 'organization.department.view',
            'organization.hierarchy.view',
            'organization.business_unit.view', 'organization.cost_center.view',
        ];
    }

    /**
     * Group observer read pack (IDORG-T06/T09) — no create/update/post.
     *
     * @return list<string>
     */
    public static function groupReadPermissionCodes(): array
    {
        return [
            self::PERM_GROUP_OBSERVE,
            'identity.profile.view',
            'identity.user.view',
            'identity.role.view',
            'identity.permission.view',
            'identity.membership_history.view',
            'organization.company.view',
            'organization.branch.view',
            'organization.department.view',
            'organization.hierarchy.view',
            'organization.business_unit.view',
            'organization.cost_center.view',
            'organization.ownership.view',
            'organization.fiscal.view',
            'accounting.voucher.view',
            'accounting.account.view',
        ];
    }

    /**
     * Company operator template — members + local operate (Scope still limits company).
     *
     * @return list<string>
     */
    public static function companyOperatePermissionCodes(): array
    {
        return [
            'identity.profile.view', 'identity.profile.update',
            'identity.user.view', 'identity.user.create', 'identity.user.update',
            'identity.role.view', 'identity.role.assign',
            'identity.membership_history.view',
            'organization.company.view', 'organization.branch.view', 'organization.department.view',
            'organization.hierarchy.view',
            'accounting.voucher.view', 'accounting.voucher.post', 'accounting.account.view',
        ];
    }

    /**
     * Company end-user template — minimal.
     *
     * @return list<string>
     */
    public static function companyUserPermissionCodes(): array
    {
        return [
            'identity.profile.view', 'identity.profile.update',
            'organization.company.view', 'organization.branch.view', 'organization.department.view',
        ];
    }

    /**
     * Map a permission code to its primary access layer (T08 tagging convention).
     */
    public static function layerForPermissionCode(string $code): string
    {
        $code = strtolower(trim($code));

        if ($code === self::PERM_GROUP_OBSERVE) {
            return self::LAYER_GROUP_READ;
        }

        if (str_starts_with($code, 'identity.role.')
            || str_starts_with($code, 'identity.scope.')
            || str_starts_with($code, 'identity.sod.')
            || str_starts_with($code, 'identity.privileged.')
            || str_starts_with($code, 'identity.access_cert.manage')
            || str_starts_with($code, 'identity.mfa.')) {
            return self::LAYER_PLATFORM_ADMIN;
        }

        if (str_ends_with($code, '.view') || str_ends_with($code, '.read')) {
            return self::LAYER_GROUP_READ;
        }

        if (str_contains($code, 'branch')) {
            return self::LAYER_BRANCH;
        }

        return self::LAYER_COMPANY_OPERATE;
    }

    /**
     * @return list<string>
     */
    public static function roleTemplateCodes(): array
    {
        return [
            self::ROLE_PLATFORM_ADMIN,
            self::ROLE_GROUP_OBSERVER,
            self::ROLE_COMPANY_OPERATOR,
            self::ROLE_COMPANY_USER,
        ];
    }
}
