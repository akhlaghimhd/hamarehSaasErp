<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PartnerLayer API Routes (Layer 3)
|--------------------------------------------------------------------------
| Loaded by ModuleServiceProvider with prefix: /api/partner-layer
| Middleware 'api' is already applied by the provider.
|
| Controllers/Services will be added in P3-A / P3-S phases.
*/

Route::middleware([
    'auth:sanctum',
    'tenant.context',
    'load.scopes',
])->group(function () {
    // P3-A1+ routes will be registered here
});
