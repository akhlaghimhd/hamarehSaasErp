<?php

use Illuminate\Support\Facades\Route;
use App\Modules\PartnerLayer\Controllers\PartnerController;
use App\Modules\PartnerLayer\Controllers\PartnerUserController;
use App\Modules\PartnerLayer\Controllers\PartnerTenantAssignmentController;
use App\Modules\PartnerLayer\Controllers\PartnerAgreementController;
use App\Modules\PartnerLayer\Controllers\PartnerCommissionRuleController;
use App\Modules\PartnerLayer\Controllers\PartnerCommissionController;
use App\Modules\PartnerLayer\Controllers\PartnerPayoutController;
use App\Modules\PartnerLayer\Controllers\PartnerBankAccountController;
use App\Modules\PartnerLayer\Controllers\PartnerContactController;
use App\Modules\PartnerLayer\Controllers\PartnerDocumentController;
use App\Modules\PartnerLayer\Controllers\PartnerActivityLogController;

/*
|--------------------------------------------------------------------------
| PartnerLayer API Routes (Layer 3)
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'tenant.context',
    'load.scopes',
])->group(function () {

    Route::get('/partners', [PartnerController::class, 'index'])->middleware('permission:partner.partner.view')->name('partner-layer.partners.index');
    Route::post('/partners', [PartnerController::class, 'store'])->middleware('permission:partner.partner.create')->name('partner-layer.partners.store');
    Route::get('/partners/{partner}', [PartnerController::class, 'show'])->middleware('permission:partner.partner.view')->name('partner-layer.partners.show');
    Route::put('/partners/{partner}', [PartnerController::class, 'update'])->middleware('permission:partner.partner.update')->name('partner-layer.partners.update');
    Route::delete('/partners/{partner}', [PartnerController::class, 'destroy'])->middleware('permission:partner.partner.delete')->name('partner-layer.partners.delete');

    Route::get('/partner-users', [PartnerUserController::class, 'index'])->middleware('permission:partner.partner_user.view')->name('partner-layer.partner-users.index');
    Route::post('/partner-users', [PartnerUserController::class, 'store'])->middleware('permission:partner.partner_user.create')->name('partner-layer.partner-users.store');
    Route::get('/partner-users/{partnerUser}', [PartnerUserController::class, 'show'])->middleware('permission:partner.partner_user.view')->name('partner-layer.partner-users.show');
    Route::put('/partner-users/{partnerUser}', [PartnerUserController::class, 'update'])->middleware('permission:partner.partner_user.update')->name('partner-layer.partner-users.update');
    Route::delete('/partner-users/{partnerUser}', [PartnerUserController::class, 'destroy'])->middleware('permission:partner.partner_user.delete')->name('partner-layer.partner-users.delete');

    Route::get('/partner-tenant-assignments', [PartnerTenantAssignmentController::class, 'index'])->middleware('permission:partner.assignment.view')->name('partner-layer.assignments.index');
    Route::post('/partner-tenant-assignments', [PartnerTenantAssignmentController::class, 'store'])->middleware('permission:partner.assignment.create')->name('partner-layer.assignments.store');
    Route::get('/partner-tenant-assignments/{assignment}', [PartnerTenantAssignmentController::class, 'show'])->middleware('permission:partner.assignment.view')->name('partner-layer.assignments.show');
    Route::put('/partner-tenant-assignments/{assignment}', [PartnerTenantAssignmentController::class, 'update'])->middleware('permission:partner.assignment.update')->name('partner-layer.assignments.update');
    Route::delete('/partner-tenant-assignments/{assignment}', [PartnerTenantAssignmentController::class, 'destroy'])->middleware('permission:partner.assignment.delete')->name('partner-layer.assignments.delete');

    Route::get('/partner-agreements', [PartnerAgreementController::class, 'index'])->middleware('permission:partner.agreement.view')->name('partner-layer.agreements.index');
    Route::post('/partner-agreements', [PartnerAgreementController::class, 'store'])->middleware('permission:partner.agreement.create')->name('partner-layer.agreements.store');
    Route::get('/partner-agreements/{agreement}', [PartnerAgreementController::class, 'show'])->middleware('permission:partner.agreement.view')->name('partner-layer.agreements.show');
    Route::put('/partner-agreements/{agreement}', [PartnerAgreementController::class, 'update'])->middleware('permission:partner.agreement.update')->name('partner-layer.agreements.update');
    Route::delete('/partner-agreements/{agreement}', [PartnerAgreementController::class, 'destroy'])->middleware('permission:partner.agreement.delete')->name('partner-layer.agreements.delete');

    Route::get('/partner-commission-rules', [PartnerCommissionRuleController::class, 'index'])->middleware('permission:partner.commission_rule.view')->name('partner-layer.commission-rules.index');
    Route::post('/partner-commission-rules', [PartnerCommissionRuleController::class, 'store'])->middleware('permission:partner.commission_rule.create')->name('partner-layer.commission-rules.store');
    Route::get('/partner-commission-rules/{commissionRule}', [PartnerCommissionRuleController::class, 'show'])->middleware('permission:partner.commission_rule.view')->name('partner-layer.commission-rules.show');
    Route::put('/partner-commission-rules/{commissionRule}', [PartnerCommissionRuleController::class, 'update'])->middleware('permission:partner.commission_rule.update')->name('partner-layer.commission-rules.update');
    Route::delete('/partner-commission-rules/{commissionRule}', [PartnerCommissionRuleController::class, 'destroy'])->middleware('permission:partner.commission_rule.delete')->name('partner-layer.commission-rules.delete');

    Route::get('/partner-commissions', [PartnerCommissionController::class, 'index'])->middleware('permission:partner.commission.view')->name('partner-layer.commissions.index');
    Route::post('/partner-commissions', [PartnerCommissionController::class, 'store'])->middleware('permission:partner.commission.create')->name('partner-layer.commissions.store');
    Route::get('/partner-commissions/{commission}', [PartnerCommissionController::class, 'show'])->middleware('permission:partner.commission.view')->name('partner-layer.commissions.show');
    Route::put('/partner-commissions/{commission}', [PartnerCommissionController::class, 'update'])->middleware('permission:partner.commission.update')->name('partner-layer.commissions.update');
    Route::delete('/partner-commissions/{commission}', [PartnerCommissionController::class, 'destroy'])->middleware('permission:partner.commission.delete')->name('partner-layer.commissions.delete');

    Route::get('/partner-payouts', [PartnerPayoutController::class, 'index'])->middleware('permission:partner.payout.view')->name('partner-layer.payouts.index');
    Route::post('/partner-payouts', [PartnerPayoutController::class, 'store'])->middleware('permission:partner.payout.create')->name('partner-layer.payouts.store');
    Route::get('/partner-payouts/{payout}', [PartnerPayoutController::class, 'show'])->middleware('permission:partner.payout.view')->name('partner-layer.payouts.show');
    Route::put('/partner-payouts/{payout}', [PartnerPayoutController::class, 'update'])->middleware('permission:partner.payout.update')->name('partner-layer.payouts.update');
    Route::delete('/partner-payouts/{payout}', [PartnerPayoutController::class, 'destroy'])->middleware('permission:partner.payout.delete')->name('partner-layer.payouts.delete');

    // P3-S8 supporting entities
    Route::get('/partner-bank-accounts', [PartnerBankAccountController::class, 'index'])->middleware('permission:partner.bank_account.view')->name('partner-layer.bank-accounts.index');
    Route::post('/partner-bank-accounts', [PartnerBankAccountController::class, 'store'])->middleware('permission:partner.bank_account.create')->name('partner-layer.bank-accounts.store');
    Route::get('/partner-bank-accounts/{bankAccount}', [PartnerBankAccountController::class, 'show'])->middleware('permission:partner.bank_account.view')->name('partner-layer.bank-accounts.show');
    Route::put('/partner-bank-accounts/{bankAccount}', [PartnerBankAccountController::class, 'update'])->middleware('permission:partner.bank_account.update')->name('partner-layer.bank-accounts.update');
    Route::delete('/partner-bank-accounts/{bankAccount}', [PartnerBankAccountController::class, 'destroy'])->middleware('permission:partner.bank_account.delete')->name('partner-layer.bank-accounts.delete');

    Route::get('/partner-contacts', [PartnerContactController::class, 'index'])->middleware('permission:partner.contact.view')->name('partner-layer.contacts.index');
    Route::post('/partner-contacts', [PartnerContactController::class, 'store'])->middleware('permission:partner.contact.create')->name('partner-layer.contacts.store');
    Route::get('/partner-contacts/{contact}', [PartnerContactController::class, 'show'])->middleware('permission:partner.contact.view')->name('partner-layer.contacts.show');
    Route::put('/partner-contacts/{contact}', [PartnerContactController::class, 'update'])->middleware('permission:partner.contact.update')->name('partner-layer.contacts.update');
    Route::delete('/partner-contacts/{contact}', [PartnerContactController::class, 'destroy'])->middleware('permission:partner.contact.delete')->name('partner-layer.contacts.delete');

    Route::get('/partner-documents', [PartnerDocumentController::class, 'index'])->middleware('permission:partner.document.view')->name('partner-layer.documents.index');
    Route::post('/partner-documents', [PartnerDocumentController::class, 'store'])->middleware('permission:partner.document.create')->name('partner-layer.documents.store');
    Route::get('/partner-documents/{document}', [PartnerDocumentController::class, 'show'])->middleware('permission:partner.document.view')->name('partner-layer.documents.show');
    Route::put('/partner-documents/{document}', [PartnerDocumentController::class, 'update'])->middleware('permission:partner.document.update')->name('partner-layer.documents.update');
    Route::delete('/partner-documents/{document}', [PartnerDocumentController::class, 'destroy'])->middleware('permission:partner.document.delete')->name('partner-layer.documents.delete');

    // Activity log: append-only (no update/delete routes)
    Route::get('/partner-activity-logs', [PartnerActivityLogController::class, 'index'])->middleware('permission:partner.activity_log.view')->name('partner-layer.activity-logs.index');
    Route::post('/partner-activity-logs', [PartnerActivityLogController::class, 'store'])->middleware('permission:partner.activity_log.create')->name('partner-layer.activity-logs.store');
    Route::get('/partner-activity-logs/{activityLog}', [PartnerActivityLogController::class, 'show'])->middleware('permission:partner.activity_log.view')->name('partner-layer.activity-logs.show');

});
