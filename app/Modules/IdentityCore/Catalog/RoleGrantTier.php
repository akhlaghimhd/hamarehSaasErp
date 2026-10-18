<?php

namespace App\Modules\IdentityCore\Catalog;

/**
 * ADR-ID-ORG-006 — Role grant tier vs organizational scope ceiling.
 *
 * Rank: branch(1) < company(2) < group(3) < tenant(4)
 * Rule: role_tier_rank <= min(actor_grant_rank, target_scope_rank)
 * Escape for higher role on child: first grant ancestor/parent COMPANY scope (raises target rank).
 */
final class RoleGrantTier
{
    public const BRANCH = 'branch';
    public const COMPANY = 'company';
    public const GROUP = 'group';
    public const TENANT = 'tenant';

    /** @var list<string> */
    private const TENANT_CODES = [
        'platform-admin',
    ];

    /** @var list<string> */
    private const GROUP_CODES = [
        'group-observer',
        'org-multi-company',
        'identity-manager',
        'identity-roles',
        'org-admin',
    ];

    /** @var list<string> */
    private const BRANCH_CODES = [
        'branch-admin',
        'branch-operator',
        'branch-user',
    ];

    public static function tierForCode(?string $code): string
    {
        $c = strtolower(trim((string) $code));
        if ($c === '') {
            return self::COMPANY;
        }
        if (in_array($c, self::TENANT_CODES, true)) {
            return self::TENANT;
        }
        if (in_array($c, self::GROUP_CODES, true)) {
            return self::GROUP;
        }
        if (in_array($c, self::BRANCH_CODES, true) || str_starts_with($c, 'branch-')) {
            return self::BRANCH;
        }

        return self::COMPANY;
    }

    public static function rank(string $tier): int
    {
        return match ($tier) {
            self::BRANCH => 1,
            self::COMPANY => 2,
            self::GROUP => 3,
            self::TENANT => 4,
            default => 2,
        };
    }

    public static function rankForCode(?string $code): int
    {
        return self::rank(self::tierForCode($code));
    }

    public static function labelFa(string $tier): string
    {
        return match ($tier) {
            self::BRANCH => 'شعبه',
            self::COMPANY => 'شرکت',
            self::GROUP => 'گروه/مادر',
            self::TENANT => 'پلتفرم/مالک فنی',
            default => $tier,
        };
    }
}
