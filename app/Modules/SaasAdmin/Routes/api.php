<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Saas Admin API Routes (Layer 2 - Platform Administration)
|--------------------------------------------------------------------------
| Loaded by ModuleServiceProvider with prefix: /api/saas-admin
| Middleware 'api' is already applied by the provider.
|
| Layer 1 routes (Tenant, Plan, Subscription, Invoice, Addon, Coupon)
| have been moved to SaasPlatform module.
| This file is intentionally left minimal until Layer 2 controllers
| (AdminUser, AdminRole, etc.) are implemented.
*/

Route::middleware(['auth:sanctum'])->group(function () {
    // Layer 2 routes will be added here later
    // Example placeholders (commented):
    // Route::get('/admin-users', [AdminUserController::class, 'index']);
});
