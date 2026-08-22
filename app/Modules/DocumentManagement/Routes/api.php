<?php
use Illuminate\Support\Facades\Route;
use App\Modules\DocumentManagement\Controllers\DocumentController;
use App\Modules\DocumentManagement\Controllers\AttachmentController;
use App\Modules\DocumentManagement\Controllers\DocumentSequenceController;
use App\Modules\DocumentManagement\Controllers\DocumentVersionController;

Route::prefix('document-management')->middleware(['api', 'auth:sanctum', 'tenant.context'])->group(function () {
    
    // مسیرهای اسناد
    Route::get('documents', [DocumentController::class, 'index']);
    Route::post('documents', [DocumentController::class, 'store']);
    Route::put('documents/{id}', [DocumentController::class, 'update']);
    Route::delete('documents/{id}', [DocumentController::class, 'destroy']);

    // مسیرهای پیوست‌ها
    Route::post('attachments', [AttachmentController::class, 'store']);
    Route::delete('attachments/{id}', [AttachmentController::class, 'destroy']);

    // مسیرهای شماره‌گذاری و نسخه‌بندی
    Route::post('sequences', [DocumentSequenceController::class, 'store']);
    Route::post('versions', [DocumentVersionController::class, 'store']);
});