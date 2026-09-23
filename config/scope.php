<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scope Enforcement Mode (Layer 2 / F2)
    |--------------------------------------------------------------------------
    |
    | gradual (Policy B): If the user has no scopes of the model's scopeType,
    | no extra filter is applied (tenant isolation remains). Enables gradual
    | rollout of Scope assignment without breaking existing access.
    |
    | strict (Policy A): For scoped models listed in strict_scope_types,
    | missing scopes of that type deny all rows (WHERE 1 = 0). Aligns with
    | Law 4.2 chain: User → Role → Permission → Scope → Resource.
    |
    | Default during Scope rollout: gradual.
    | Target after full Scope assignment + F3 Validate Scope: strict.
    |
    | Env: SCOPE_ENFORCEMENT_MODE=gradual|strict
    |
    */
    'enforcement_mode' => env('SCOPE_ENFORCEMENT_MODE', 'gradual'),

    /*
    |--------------------------------------------------------------------------
    | Scope types subject to strict denial when mode is strict
    |--------------------------------------------------------------------------
    | BUSINESS_UNIT is recognized by Organization (ORG-P6-02) but NOT forced
    | into strict list yet — keep gradual until Identity assigns BU scopes.
    */
    'strict_scope_types' => [
        'COMPANY',
        'BRANCH',
        'WAREHOUSE',
    ],

    /*
    | Registered scope types (documentation + helpers). Free-string in DB;
    | this list is the platform catalog for UI/seeders.
    */
    'registered_scope_types' => [
        'COMPANY',
        'BRANCH',
        'DEPARTMENT',
        'WAREHOUSE',
        'BUSINESS_UNIT',
    ],

];
