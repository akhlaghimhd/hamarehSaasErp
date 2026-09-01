<?php
namespace App\Modules\DocumentManagement\Services;

use App\Modules\DocumentManagement\Models\Document;
use App\Modules\DocumentManagement\Models\Attachment;
use App\Modules\DocumentManagement\DTOs\CreateDocumentDTO;
use App\Modules\DocumentManagement\DTOs\CreateAttachmentDTO;
use App\Modules\DocumentManagement\DTOs\UpdateDocumentDTO;
use App\Modules\DocumentManagement\DTOs\CreateDocumentSequenceDTO;
use App\Modules\DocumentManagement\DTOs\CreateDocumentVersionDTO;
use App\Base\Context\TenantContext;

class DocumentManagementService
{
    public function createDocument(CreateDocumentDTO $dto): Document
    {
        $tenantId = TenantContext::getTenantId();
        
        if (Document::where('tenant_id', $tenantId)->where('document_number', $dto->documentNumber)->exists()) {
            throw new \Exception("شماره سند تکراری است.");
        }

        return Document::create([
            'tenant_id' => $tenantId,
            'document_number' => $dto->documentNumber,
            'title' => $dto->title,
            'description' => $dto->description,
            'document_type' => $dto->documentType,
            'status' => $dto->status,
        ]);
    }

    public function getDocumentById(string $documentId): Document
    {
        return Document::where('tenant_id', TenantContext::getTenantId())
            ->where('document_id', $documentId)
            ->firstOrFail();
    }

    public function createAttachment(CreateAttachmentDTO $dto): Attachment
    {
        // در یک سیستم واقعی، اینجا ابتدا هش فایل چک می‌شود تا فایل تکراری ذخیره نشود
        return Attachment::create([
            'tenant_id' => TenantContext::getTenantId(),
            'target_entity_type' => $dto->targetEntityType,
            'target_entity_id' => $dto->targetEntityId,
            'file_name' => $dto->fileName,
            'file_path' => $dto->filePath,
            'mime_type' => $dto->mimeType,
            'file_size_bytes' => $dto->fileSizeBytes,
            'file_hash' => $dto->fileHash,
        ]);
    }

    public function getAllDocuments()
    {
        return Document::where('tenant_id', TenantContext::getTenantId())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function updateDocument(string $documentId, UpdateDocumentDTO $dto): Document
    {
        $tenantId = TenantContext::getTenantId();
        $document = Document::where('tenant_id', $tenantId)->where('document_id', $documentId)->firstOrFail();

        // بررسی یکتا بودن شماره سند جدید در صورت تغییر
        if ($document->document_number !== $dto->documentNumber) {
            if (Document::where('tenant_id', $tenantId)->where('document_number', $dto->documentNumber)->exists()) {
                throw new \Exception("شماره سند تکراری است.");
            }
        }

        $document->update([
            'document_number' => $dto->documentNumber,
            'title' => $dto->title,
            'description' => $dto->description,
            'document_type' => $dto->documentType,
            'status' => $dto->status,
        ]);

        return $document;
    }

    public function deleteDocument(string $documentId): void
    {
        $document = Document::where('tenant_id', TenantContext::getTenantId())
            ->where('document_id', $documentId)
            ->firstOrFail();
            
        $document->delete();
    }

    public function deleteAttachment(string $attachmentId): void
    {
        $attachment = Attachment::where('tenant_id', TenantContext::getTenantId())
            ->where('attachment_id', $attachmentId)
            ->firstOrFail();
            
        $attachment->delete();
    }

    public function createSequence(CreateDocumentSequenceDTO $dto): \App\Modules\DocumentManagement\Models\DocumentSequence
    {
        $tenantId = TenantContext::getTenantId();

        // جلوگیری از ایجاد Sequence تکراری بر اساس قوانین اسناد معماری (ترکیب فیلدها)
        $exists = \App\Modules\DocumentManagement\Models\DocumentSequence::where('tenant_id', $tenantId)
            ->where('module_code', $dto->moduleCode)
            ->where('document_type', $dto->documentType)
            ->where('document_scope', $dto->documentScope)
            ->where('company_id', $dto->companyId)
            ->exists();

        if ($exists) {
            throw new \Exception("یک موتور شماره‌گذاری با این مشخصات و دامنه قبلاً تعریف شده است.");
        }

        return \App\Modules\DocumentManagement\Models\DocumentSequence::create([
            'tenant_id' => $tenantId,
            'module_code' => $dto->moduleCode,
            'document_type' => $dto->documentType,
            'document_scope' => $dto->documentScope,
            'company_id' => $dto->companyId,
            'owner_type' => $dto->ownerType,
            'owner_id' => $dto->ownerId,
            'prefix' => $dto->prefix,
            'suffix' => $dto->suffix,
            'padding_length' => $dto->paddingLength,
            'reset_period' => $dto->resetPeriod,
        ]);
    }

    public function createVersion(CreateDocumentVersionDTO $dto): \App\Modules\DocumentManagement\Models\DocumentVersion
    {
        $tenantId = TenantContext::getTenantId();

        // جلوگیری از ثبت نسخه تکراری برای یک سند
        $exists = \App\Modules\DocumentManagement\Models\DocumentVersion::where('tenant_id', $tenantId)
            ->where('document_id', $dto->documentId)
            ->where('version_number', $dto->versionNumber)
            ->exists();

        if ($exists) {
            throw new \Exception("این شماره نسخه برای سند مورد نظر قبلاً ثبت شده است.");
        }

        return \App\Modules\DocumentManagement\Models\DocumentVersion::create([
            'tenant_id' => $tenantId,
            'document_id' => $dto->documentId,
            'version_number' => $dto->versionNumber,
            'attachment_id' => $dto->attachmentId,
            'change_summary' => $dto->changeSummary,
        ]);
    }
}
