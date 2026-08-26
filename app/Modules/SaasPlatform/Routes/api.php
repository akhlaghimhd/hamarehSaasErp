<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SaaS Platform API Routes (Layer 1 - SaaS Business)
|--------------------------------------------------------------------------
| Loaded by ModuleServiceProvider with prefix: /api/saas-platform
| Middleware 'api' is already applied by the provider.
|
| Placeholder only. Routes will be moved from SaasAdmin in Step 2.
| Permission dual-support (saas-admin.* + saas-platform.*) will be applied then.
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {
    // Routes transferred in Step 2
});