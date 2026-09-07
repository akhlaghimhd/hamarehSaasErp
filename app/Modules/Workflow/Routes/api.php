<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Workflow\Controllers\WorkflowController;

/*
|--------------------------------------------------------------------------
| Workflow Module API Routes
|--------------------------------------------------------------------------
| Prefix applied by ModuleServiceProvider: /api/workflow
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {
    Route::post('definitions', [WorkflowController::class, 'upsertDefinition'])
        ->middleware('permission:workflow.definition.manage');

    Route::post('instances', [WorkflowController::class, 'startInstance'])
        ->middleware('permission:workflow.instance.start');

    Route::get('instances/{id}', [WorkflowController::class, 'showInstance'])
        ->middleware('permission:workflow.instance.view');

    Route::get('worklist', [WorkflowController::class, 'worklist'])
        ->middleware('permission:workflow.task.view');

    Route::get('my-worklist', [WorkflowController::class, 'myWorklist'])
        ->middleware('permission:workflow.task.view');

    Route::post('tasks/{id}/complete', [WorkflowController::class, 'completeTask'])
        ->middleware('permission:workflow.task.complete');
});
