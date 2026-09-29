<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scope Enforcement Mode (Layer 2 / F2)
    |--------------------------------------------------------------------------
    |
    | gradual (Policy B): If the user has no scopes of the model's scopeType,
    | no extra filter is applied (tenant isolation remains).
    |
    | strict (Policy A): For types in strict_scope_types, missing scopes of
    | that type deny all rows (WHERE 1 = 0).
    |
    | Env: SCOPE_ENFORCEMENT_MODE=gradual|strict
    |
    */
    'enforcement_mode' => env('SCOPE_ENFORCEMENT_MODE', 'gradual'),

    /*
    |--------------------------------------------------------------------------
    | Scope types subject to strict denial when mode is strict
    |--------------------------------------------------------------------------
    | ORG-W2-04: BUSINESS_UNIT added — when SCOPE_ENFORCEMENT_MODE=strict,
    | users without any BUSINESS_UNIT scope see zero BUs (owners exempt).
    | Under gradual (default), having BU scopes still filters to those ids.
    */
    'strict_scope_types' => [
        'COMPANY',
        'BRANCH',
        'WAREHOUSE',
        'BUSINESS_UNIT',
    ],

    'registered_scope_types' => [
        'COMPANY',
        'BRANCH',
        'DEPARTMENT',
        'WAREHOUSE',
        'BUSINESS_UNIT',
        'COST_CENTER',
    ],

];
