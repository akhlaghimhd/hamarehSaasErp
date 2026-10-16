<?php

namespace App\Modules\IdentityCore\Catalog;

/**
 * IDORG-T05 / G05 + T08 / G17 + FIN-HOLD-W5 (ADR-FIN-003 L12).
 *
 * Layers:
 * - PLATFORM_ADMIN  : technical owner / tenant security admin (not FIN operate-all)
 * - GROUP_READ      : group observer — list/report; no mutate
 * - COMPANY_OPERATE : write/manage within actor COMPANY scopes only
 * - BRANCH          : branch-scoped admin (Wave 3)
 *
 * FIN-HOLD-W5: prefer finance.* codes for FinancialAccounting module;
 * legacy accounting.* kept for BC with old Accounting module surfaces.
 */
final class AccessLayerCatalog
{
    public const LAYER_PLATFORM_ADMIN = 'platform_admin';

    public const LAYER_GROUP_READ = 'group_read';

    public const LAYER_COMPANY_OPERATE = 'company_operate';

    public const LAYER_BRANCH = 'branch';

    public const PERM_GROUP_OBSERVE = 'identity.group.observe';

    public const ROLE_GROUP_OBSERVER = 'group-observer';

    public const ROLE_COMPANY_OPERATOR = 'company-operator';

    public const ROLE_COMPANY_USER = 'company-user';

    public const ROLE_PLATFORM_ADMIN = 'platform-admin';

    /**
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
     * Group observer — READ only (no create/update/post/delete/execute).
     *
     * @return list<string>
     */
    public static function groupReadPermissionCodes(): array
    {
        return array_values(array_unique(array_merge(
            [
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
                // Legacy Accounting module (BC)
                'accounting.voucher.view',
                'accounting.account.view',
            ],
            self::financeViewPermissionCodes()
        )));
    }

    /**
     * Company operator — members + local operate (Scope still limits company).
     *
     * @return list<string>
     */
    public static function companyOperatePermissionCodes(): array
    {
        return array_values(array_unique(array_merge(
            [
                'identity.profile.view', 'identity.profile.update',
                'identity.user.view', 'identity.user.create', 'identity.user.update',
                'identity.role.view', 'identity.role.assign',
                'identity.membership_history.view',
                'organization.company.view', 'organization.branch.view', 'organization.department.view',
                'organization.hierarchy.view',
                // Legacy Accounting module (BC)
                'accounting.voucher.view', 'accounting.voucher.post', 'accounting.account.view',
            ],
            self::financeViewPermissionCodes(),
            self::financeOperatePermissionCodes()
        )));
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
     * FinancialAccounting read surface (FIN-HOLD-W5 / L12).
     *
     * @return list<string>
     */
    public static function financeViewPermissionCodes(): array
    {
        return [
            'finance.coa.view',
            'finance.journal.view',
            'finance.period.view',
            'finance.report.view',
            'finance.treasury.view',
            'finance.ar.view',
            'finance.ap.view',
            'finance.tax.view',
            'finance.moodian.view',
            'finance.compliance.view',
            'finance.suggest.view',
            'finance.fa.view',
            'finance.ic.view',
            'finance.smart.view',
        ];
    }

    /**
     * FinancialAccounting mutate/execute surface — company operate only.
     *
     * @return list<string>
     */
    public static function financeOperatePermissionCodes(): array
    {
        return [
            'finance.coa.create', 'finance.coa.update', 'finance.coa.delete',
            'finance.journal.create', 'finance.journal.update', 'finance.journal.delete',
            'finance.journal.post', 'finance.journal.reverse',
            'finance.period.close', 'finance.period.reopen',
            'finance.treasury.manage', 'finance.treasury.post',
            'finance.ar.manage', 'finance.ap.manage',
            'finance.tax.manage',
            'finance.moodian.submit',
            'finance.compliance.manage',
            'finance.suggest.manage', 'finance.suggest.decide',
            'finance.fa.manage', 'finance.fa.depreciate',
            'finance.ic.manage',
            'finance.smart.decide', 'finance.smart.nl',
        ];
    }

    /**
     * True if code is a finance mutate/execute permission (must not appear on group observer).
     */
    public static function isFinanceOperatePermission(string $code): bool
    {
        return in_array(strtolower(trim($code)), self::financeOperatePermissionCodes(), true);
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

        if (self::isFinanceOperatePermission($code)) {
            return self::LAYER_COMPANY_OPERATE;
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
