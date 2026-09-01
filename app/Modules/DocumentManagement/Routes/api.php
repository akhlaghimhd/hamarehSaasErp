<?php

use Illuminate\Support\Facades\Route;
use App\Modules\DocumentManagement\Controllers\DocumentController;
use App\Modules\DocumentManagement\Controllers\AttachmentController;
use App\Modules\DocumentManagement\Controllers\DocumentSequenceController;
use App\Modules\DocumentManagement\Controllers\DocumentVersionController;

/*
|--------------------------------------------------------------------------
| Document Management API Routes
|--------------------------------------------------------------------------
| Prefix is applied by ModuleServiceProvider: /api/document-management
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    // Documents
    Route::get('documents', [DocumentController::class, 'index'])
        ->middleware('permission:document-management.document.view');
    Route::post('documents', [DocumentController::class, 'store'])
        ->middleware('permission:document-management.document.create');
    Route::get('documents/{id}', [DocumentController::class, 'show'])
        ->middleware('permission:document-management.document.view');
    Route::put('documents/{id}', [DocumentController::class, 'update'])
        ->middleware('permission:document-management.document.update');
    Route::delete('documents/{id}', [DocumentController::class, 'destroy'])
        ->middleware('permission:document-management.document.delete');

    // Attachments
    Route::post('attachments', [AttachmentController::class, 'store'])
        ->middleware('permission:document-management.attachment.create');
    Route::delete('attachments/{id}', [AttachmentController::class, 'destroy'])
        ->middleware('permission:document-management.attachment.delete');

    // Sequences & Versions
    Route::post('sequences', [DocumentSequenceController::class, 'store'])
        ->middleware('permission:document-management.sequence.create');
    Route::post('versions', [DocumentVersionController::class, 'store'])
        ->middleware('permission:document-management.version.create');
});
