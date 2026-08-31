<?php

use Illuminate\Support\Facades\Route;
use App\Modules\PartnerLayer\Controllers\PartnerController;
use App\Modules\PartnerLayer\Controllers\PartnerUserController;
use App\Modules\PartnerLayer\Controllers\PartnerTenantAssignmentController;
use App\Modules\PartnerLayer\Controllers\PartnerAgreementController;
use App\Modules\PartnerLayer\Controllers\PartnerCommissionRuleController;
use App\Modules\PartnerLayer\Controllers\PartnerCommissionController;

/*
|--------------------------------------------------------------------------
| PartnerLayer API Routes (Layer 3)
|--------------------------------------------------------------------------
| Loaded by ModuleServiceProvider with prefix: /api/partner-layer
| Middleware 'api' is already applied by the provider.
*/

Route::middleware([
    'auth:sanctum',
    'tenant.context',
    'load.scopes',
])->group(function () {

    // Partner core
    Route::get('/partners', [PartnerController::class, 'index'])
        ->middleware('permission:partner.partner.view')
        ->name('partner-layer.partners.index');

    Route::post('/partners', [PartnerController::class, 'store'])
        ->middleware('permission:partner.partner.create')
        ->name('partner-layer.partners.store');

    Route::get('/partners/{partner}', [PartnerController::class, 'show'])
        ->middleware('permission:partner.partner.view')
        ->name('partner-layer.partners.show');

    Route::put('/partners/{partner}', [PartnerController::class, 'update'])
        ->middleware('permission:partner.partner.update')
        ->name('partner-layer.partners.update');

    Route::delete('/partners/{partner}', [PartnerController::class, 'destroy'])
        ->middleware('permission:partner.partner.delete')
        ->name('partner-layer.partners.delete');

    // PartnerUser
    Route::get('/partner-users', [PartnerUserController::class, 'index'])
        ->middleware('permission:partner.partner_user.view')
        ->name('partner-layer.partner-users.index');

    Route::post('/partner-users', [PartnerUserController::class, 'store'])
        ->middleware('permission:partner.partner_user.create')
        ->name('partner-layer.partner-users.store');

    Route::get('/partner-users/{partnerUser}', [PartnerUserController::class, 'show'])
        ->middleware('permission:partner.partner_user.view')
        ->name('partner-layer.partner-users.show');

    Route::put('/partner-users/{partnerUser}', [PartnerUserController::class, 'update'])
        ->middleware('permission:partner.partner_user.update')
        ->name('partner-layer.partner-users.update');

    Route::delete('/partner-users/{partnerUser}', [PartnerUserController::class, 'destroy'])
        ->middleware('permission:partner.partner_user.delete')
        ->name('partner-layer.partner-users.delete');

    // PartnerTenantAssignment
    Route::get('/partner-tenant-assignments', [PartnerTenantAssignmentController::class, 'index'])
        ->middleware('permission:partner.assignment.view')
        ->name('partner-layer.assignments.index');

    Route::post('/partner-tenant-assignments', [PartnerTenantAssignmentController::class, 'store'])
        ->middleware('permission:partner.assignment.create')
        ->name('partner-layer.assignments.store');

    Route::get('/partner-tenant-assignments/{assignment}', [PartnerTenantAssignmentController::class, 'show'])
        ->middleware('permission:partner.assignment.view')
        ->name('partner-layer.assignments.show');

    Route::put('/partner-tenant-assignments/{assignment}', [PartnerTenantAssignmentController::class, 'update'])
        ->middleware('permission:partner.assignment.update')
        ->name('partner-layer.assignments.update');

    Route::delete('/partner-tenant-assignments/{assignment}', [PartnerTenantAssignmentController::class, 'destroy'])
        ->middleware('permission:partner.assignment.delete')
        ->name('partner-layer.assignments.delete');

    // PartnerAgreement
    Route::get('/partner-agreements', [PartnerAgreementController::class, 'index'])
        ->middleware('permission:partner.agreement.view')
        ->name('partner-layer.agreements.index');

    Route::post('/partner-agreements', [PartnerAgreementController::class, 'store'])
        ->middleware('permission:partner.agreement.create')
        ->name('partner-layer.agreements.store');

    Route::get('/partner-agreements/{agreement}', [PartnerAgreementController::class, 'show'])
        ->middleware('permission:partner.agreement.view')
        ->name('partner-layer.agreements.show');

    Route::put('/partner-agreements/{agreement}', [PartnerAgreementController::class, 'update'])
        ->middleware('permission:partner.agreement.update')
        ->name('partner-layer.agreements.update');

    Route::delete('/partner-agreements/{agreement}', [PartnerAgreementController::class, 'destroy'])
        ->middleware('permission:partner.agreement.delete')
        ->name('partner-layer.agreements.delete');

    // PartnerCommissionRule
    Route::get('/partner-commission-rules', [PartnerCommissionRuleController::class, 'index'])
        ->middleware('permission:partner.commission_rule.view')
        ->name('partner-layer.commission-rules.index');

    Route::post('/partner-commission-rules', [PartnerCommissionRuleController::class, 'store'])
        ->middleware('permission:partner.commission_rule.create')
        ->name('partner-layer.commission-rules.store');

    Route::get('/partner-commission-rules/{commissionRule}', [PartnerCommissionRuleController::class, 'show'])
        ->middleware('permission:partner.commission_rule.view')
        ->name('partner-layer.commission-rules.show');

    Route::put('/partner-commission-rules/{commissionRule}', [PartnerCommissionRuleController::class, 'update'])
        ->middleware('permission:partner.commission_rule.update')
        ->name('partner-layer.commission-rules.update');

    Route::delete('/partner-commission-rules/{commissionRule}', [PartnerCommissionRuleController::class, 'destroy'])
        ->middleware('permission:partner.commission_rule.delete')
        ->name('partner-layer.commission-rules.delete');

    // PartnerCommission
    Route::get('/partner-commissions', [PartnerCommissionController::class, 'index'])
        ->middleware('permission:partner.commission.view')
        ->name('partner-layer.commissions.index');

    Route::post('/partner-commissions', [PartnerCommissionController::class, 'store'])
        ->middleware('permission:partner.commission.create')
        ->name('partner-layer.commissions.store');

    Route::get('/partner-commissions/{commission}', [PartnerCommissionController::class, 'show'])
        ->middleware('permission:partner.commission.view')
        ->name('partner-layer.commissions.show');

    Route::put('/partner-commissions/{commission}', [PartnerCommissionController::class, 'update'])
        ->middleware('permission:partner.commission.update')
        ->name('partner-layer.commissions.update');

    Route::delete('/partner-commissions/{commission}', [PartnerCommissionController::class, 'destroy'])
        ->middleware('permission:partner.commission.delete')
        ->name('partner-layer.commissions.delete');

});
