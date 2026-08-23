<?php

use Illuminate\Support\Facades\Route;
use App\Modules\DocumentManagement\Controllers\DocumentController;
use App\Modules\DocumentManagement\Controllers\AttachmentController;
use App\Modules\DocumentManagement\Controllers\DocumentSequenceController;
use App\Modules\DocumentManagement\Controllers\DocumentVersionController;

Route::prefix('document-management')
    ->middleware(['api', 'auth:sanctum', 'tenant.context'])
    ->group(function () {
    
        // مسیرهای اسناد
        Route::get('documents', [DocumentController::class, 'index'])
            ->middleware('permission:document-management.document.view');
        Route::post('documents', [DocumentController::class, 'store'])
            ->middleware('permission:document-management.document.create');
        Route::put('documents/{id}', [DocumentController::class, 'update'])
            ->middleware('permission:document-management.document.update');
        Route::delete('documents/{id}', [DocumentController::class, 'destroy'])
            ->middleware('permission:document-management.document.delete');

        // مسیرهای پیوست‌ها
        Route::post('attachments', [AttachmentController::class, 'store'])
            ->middleware('permission:document-management.attachment.create');
        Route::delete('attachments/{id}', [AttachmentController::class, 'destroy'])
            ->middleware('permission:document-management.attachment.delete');

        // مسیرهای شماره‌گذاری و نسخه‌بندی
        Route::post('sequences', [DocumentSequenceController::class, 'store'])
            ->middleware('permission:document-management.sequence.create');
        Route::post('versions', [DocumentVersionController::class, 'store'])
            ->middleware('permission:document-management.version.create');
    });