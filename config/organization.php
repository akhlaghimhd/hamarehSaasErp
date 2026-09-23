<?php

/**
 * Organization module platform conventions (ORG-P6-03 share policy).
 *
 * master_data_share_mode:
 *   company_specific — default; master data rows are company-scoped where applicable
 *   shared_within_tenant — allow cross-company read of designated shared catalogs inside tenant
 */
return [

    'master_data_share_mode' => env('ORG_MASTER_DATA_SHARE_MODE', 'company_specific'),

    /*
    | Known Scope types used by Organization (Identity tenant_scopes.scope_type).
    | BUSINESS_UNIT is registered here for P6 wiring; enforcement remains gradual
    | until Identity assigns scopes and P6 strict rollout.
    */
    'scope_types' => [
        'COMPANY',
        'BRANCH',
        'DEPARTMENT',
        'BUSINESS_UNIT',
        'WAREHOUSE',
    ],

    /*
    | Integration event types published toward Accounting (ORG-P6-04 / P6-05)
    */
    'events' => [
        'elimination_requested' => 'organization.elimination.requested.v1',
        'consolidation_snapshotted' => 'organization.consolidation.snapshotted.v1',
        'structure_template_applied' => 'organization.structure.template_applied.v1',
    ],

];
