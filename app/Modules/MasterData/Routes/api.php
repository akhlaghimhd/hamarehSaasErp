<?php

use Illuminate\Support\Facades\Route;
use App\Modules\MasterData\Controllers\BusinessPartnerController;
use App\Modules\MasterData\Controllers\ItemController;
use App\Modules\MasterData\Controllers\CostCenterController;
use App\Modules\MasterData\Controllers\WarehouseController;
use App\Modules\MasterData\Controllers\CurrencyController;
use App\Modules\MasterData\Controllers\CountryController;
use App\Modules\MasterData\Controllers\TaxCategoryController;
use App\Modules\MasterData\Controllers\TaxDefinitionController;
use App\Modules\MasterData\Controllers\BankAccountController;
use App\Modules\MasterData\Controllers\EntityAddressController;
use App\Modules\MasterData\Controllers\EntityContactPointController;
use App\Modules\MasterData\Controllers\TagController;
use App\Modules\MasterData\Controllers\EntityTagController;
use App\Modules\MasterData\Controllers\MasterDataCategoryController;
use App\Modules\MasterData\Controllers\MasterDataValueController;

/*
|--------------------------------------------------------------------------
| Master Data API Routes
|--------------------------------------------------------------------------
| Prefix پیش‌فرض توسط ModuleServiceProvider: /api/master-data
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    // Business Partners
    Route::get('business-partners', [BusinessPartnerController::class, 'index'])
        ->middleware('permission:master-data.business-partner.view');
    Route::post('business-partners', [BusinessPartnerController::class, 'store'])
        ->middleware('permission:master-data.business-partner.create');
    Route::get('business-partners/{id}', [BusinessPartnerController::class, 'show'])
        ->middleware('permission:master-data.business-partner.view');
    Route::put('business-partners/{id}', [BusinessPartnerController::class, 'update'])
        ->middleware('permission:master-data.business-partner.update');
    Route::delete('business-partners/{id}', [BusinessPartnerController::class, 'destroy'])
        ->middleware('permission:master-data.business-partner.delete');

    // Items
    Route::get('items', [ItemController::class, 'index'])
        ->middleware('permission:master-data.item.view');
    Route::post('items', [ItemController::class, 'store'])
        ->middleware('permission:master-data.item.create');
    Route::get('items/{id}', [ItemController::class, 'show'])
        ->middleware('permission:master-data.item.view');
    Route::put('items/{id}', [ItemController::class, 'update'])
        ->middleware('permission:master-data.item.update');
    Route::delete('items/{id}', [ItemController::class, 'destroy'])
        ->middleware('permission:master-data.item.delete');

    // Cost Centers
    Route::get('cost-centers', [CostCenterController::class, 'index'])
        ->middleware('permission:master-data.cost-center.view');
    Route::post('cost-centers', [CostCenterController::class, 'store'])
        ->middleware('permission:master-data.cost-center.create');
    Route::get('cost-centers/{id}', [CostCenterController::class, 'show'])
        ->middleware('permission:master-data.cost-center.view');
    Route::put('cost-centers/{id}', [CostCenterController::class, 'update'])
        ->middleware('permission:master-data.cost-center.update');
    Route::delete('cost-centers/{id}', [CostCenterController::class, 'destroy'])
        ->middleware('permission:master-data.cost-center.delete');

    // Warehouses
    Route::get('warehouses', [WarehouseController::class, 'index'])
        ->middleware('permission:master-data.warehouse.view');
    Route::post('warehouses', [WarehouseController::class, 'store'])
        ->middleware('permission:master-data.warehouse.create');
    Route::get('warehouses/{id}', [WarehouseController::class, 'show'])
        ->middleware('permission:master-data.warehouse.view');
    Route::put('warehouses/{id}', [WarehouseController::class, 'update'])
        ->middleware('permission:master-data.warehouse.update');
    Route::delete('warehouses/{id}', [WarehouseController::class, 'destroy'])
        ->middleware('permission:master-data.warehouse.delete');

    // Currencies (Platform Master Data — no tenant_id)
    Route::get('currencies', [CurrencyController::class, 'index'])
        ->middleware('permission:master-data.currency.view');
    Route::post('currencies', [CurrencyController::class, 'store'])
        ->middleware('permission:master-data.currency.create');
    Route::get('currencies/{id}', [CurrencyController::class, 'show'])
        ->middleware('permission:master-data.currency.view');
    Route::put('currencies/{id}', [CurrencyController::class, 'update'])
        ->middleware('permission:master-data.currency.update');
    Route::delete('currencies/{id}', [CurrencyController::class, 'destroy'])
        ->middleware('permission:master-data.currency.delete');

    // Countries (Platform Master Data — no tenant_id)
    Route::get('countries', [CountryController::class, 'index'])
        ->middleware('permission:master-data.country.view');
    Route::post('countries', [CountryController::class, 'store'])
        ->middleware('permission:master-data.country.create');
    Route::get('countries/{id}', [CountryController::class, 'show'])
        ->middleware('permission:master-data.country.view');
    Route::put('countries/{id}', [CountryController::class, 'update'])
        ->middleware('permission:master-data.country.update');
    Route::delete('countries/{id}', [CountryController::class, 'destroy'])
        ->middleware('permission:master-data.country.delete');

    // Tax Categories (Tenant-Owned)
    Route::get('tax-categories', [TaxCategoryController::class, 'index'])
        ->middleware('permission:master-data.tax-category.view');
    Route::post('tax-categories', [TaxCategoryController::class, 'store'])
        ->middleware('permission:master-data.tax-category.create');
    Route::get('tax-categories/{id}', [TaxCategoryController::class, 'show'])
        ->middleware('permission:master-data.tax-category.view');
    Route::put('tax-categories/{id}', [TaxCategoryController::class, 'update'])
        ->middleware('permission:master-data.tax-category.update');
    Route::delete('tax-categories/{id}', [TaxCategoryController::class, 'destroy'])
        ->middleware('permission:master-data.tax-category.delete');

    // Tax Definitions (Tenant-Owned)
    Route::get('tax-definitions', [TaxDefinitionController::class, 'index'])
        ->middleware('permission:master-data.tax-definition.view');
    Route::post('tax-definitions', [TaxDefinitionController::class, 'store'])
        ->middleware('permission:master-data.tax-definition.create');
    Route::get('tax-definitions/{id}', [TaxDefinitionController::class, 'show'])
        ->middleware('permission:master-data.tax-definition.view');
    Route::put('tax-definitions/{id}', [TaxDefinitionController::class, 'update'])
        ->middleware('permission:master-data.tax-definition.update');
    Route::delete('tax-definitions/{id}', [TaxDefinitionController::class, 'destroy'])
        ->middleware('permission:master-data.tax-definition.delete');

    // Bank Accounts (L5-MD-S01)
    Route::get('bank-accounts', [BankAccountController::class, 'index'])
        ->middleware('permission:master-data.bank-account.view');
    Route::post('bank-accounts', [BankAccountController::class, 'store'])
        ->middleware('permission:master-data.bank-account.create');
    Route::get('bank-accounts/{id}', [BankAccountController::class, 'show'])
        ->middleware('permission:master-data.bank-account.view');
    Route::put('bank-accounts/{id}', [BankAccountController::class, 'update'])
        ->middleware('permission:master-data.bank-account.update');
    Route::delete('bank-accounts/{id}', [BankAccountController::class, 'destroy'])
        ->middleware('permission:master-data.bank-account.delete');

    // Entity Addresses (L5-MD-S02)
    Route::get('entity-addresses', [EntityAddressController::class, 'index'])
        ->middleware('permission:master-data.entity-address.view');
    Route::post('entity-addresses', [EntityAddressController::class, 'store'])
        ->middleware('permission:master-data.entity-address.create');
    Route::get('entity-addresses/{id}', [EntityAddressController::class, 'show'])
        ->middleware('permission:master-data.entity-address.view');
    Route::put('entity-addresses/{id}', [EntityAddressController::class, 'update'])
        ->middleware('permission:master-data.entity-address.update');
    Route::delete('entity-addresses/{id}', [EntityAddressController::class, 'destroy'])
        ->middleware('permission:master-data.entity-address.delete');

    // Entity Contact Points (L5-MD-S03)
    Route::get('entity-contact-points', [EntityContactPointController::class, 'index'])
        ->middleware('permission:master-data.entity-contact-point.view');
    Route::post('entity-contact-points', [EntityContactPointController::class, 'store'])
        ->middleware('permission:master-data.entity-contact-point.create');
    Route::get('entity-contact-points/{id}', [EntityContactPointController::class, 'show'])
        ->middleware('permission:master-data.entity-contact-point.view');
    Route::put('entity-contact-points/{id}', [EntityContactPointController::class, 'update'])
        ->middleware('permission:master-data.entity-contact-point.update');
    Route::delete('entity-contact-points/{id}', [EntityContactPointController::class, 'destroy'])
        ->middleware('permission:master-data.entity-contact-point.delete');

    // Tags (L5-MD-S04)
    Route::get('tags', [TagController::class, 'index'])
        ->middleware('permission:master-data.tag.view');
    Route::post('tags', [TagController::class, 'store'])
        ->middleware('permission:master-data.tag.create');
    Route::get('tags/{id}', [TagController::class, 'show'])
        ->middleware('permission:master-data.tag.view');
    Route::put('tags/{id}', [TagController::class, 'update'])
        ->middleware('permission:master-data.tag.update');
    Route::delete('tags/{id}', [TagController::class, 'destroy'])
        ->middleware('permission:master-data.tag.delete');

    // Entity Tags (L5-MD-S05) – attach/detach style
    Route::get('entity-tags', [EntityTagController::class, 'index'])
        ->middleware('permission:master-data.entity-tag.view');
    Route::post('entity-tags', [EntityTagController::class, 'store'])
        ->middleware('permission:master-data.entity-tag.create');
    Route::delete('entity-tags/{id}', [EntityTagController::class, 'destroy'])
        ->middleware('permission:master-data.entity-tag.delete');

    // Master Data Categories (L5-MD-S06)
    Route::get('master-data-categories', [MasterDataCategoryController::class, 'index'])
        ->middleware('permission:master-data.master-data-category.view');
    Route::post('master-data-categories', [MasterDataCategoryController::class, 'store'])
        ->middleware('permission:master-data.master-data-category.create');
    Route::get('master-data-categories/{id}', [MasterDataCategoryController::class, 'show'])
        ->middleware('permission:master-data.master-data-category.view');
    Route::put('master-data-categories/{id}', [MasterDataCategoryController::class, 'update'])
        ->middleware('permission:master-data.master-data-category.update');
    Route::delete('master-data-categories/{id}', [MasterDataCategoryController::class, 'destroy'])
        ->middleware('permission:master-data.master-data-category.delete');

    // Master Data Values (L5-MD-S07)
    Route::get('master-data-values', [MasterDataValueController::class, 'index'])
        ->middleware('permission:master-data.master-data-value.view');
    Route::post('master-data-values', [MasterDataValueController::class, 'store'])
        ->middleware('permission:master-data.master-data-value.create');
    Route::get('master-data-values/{id}', [MasterDataValueController::class, 'show'])
        ->middleware('permission:master-data.master-data-value.view');
    Route::put('master-data-values/{id}', [MasterDataValueController::class, 'update'])
        ->middleware('permission:master-data.master-data-value.update');
    Route::delete('master-data-values/{id}', [MasterDataValueController::class, 'destroy'])
        ->middleware('permission:master-data.master-data-value.delete');
});
