<?php

use App\Modules\ProjectManagement\Controllers\ProjectController;
use App\Modules\ProjectManagement\Controllers\ProjectTaskController;
use App\Modules\ProjectManagement\Controllers\ProjectMemberController;
use App\Modules\ProjectManagement\Controllers\ResourceAllocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/project-management')
    ->middleware(['api', 'auth:api', 'tenant_context'])
    ->group(function () {
        
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::post('/tasks', [ProjectTaskController::class, 'store']);
        Route::post('/members', [ProjectMemberController::class, 'store']);
        
        // Resource Allocations
        Route::post('/resource-allocations', [ResourceAllocationController::class, 'store']);
        
    });