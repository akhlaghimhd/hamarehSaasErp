<?php

namespace App\Modules\PartnerLayer\Services;

use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerDocument;
use App\Modules\PartnerLayer\DTOs\CreatePartnerDocumentDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerDocumentDTO;
use App\Base\Context\TenantContext;
use Exception;
use Illuminate\Support\Collection;

/**
 * P3-S8 — Partner documents.
 * verified_by is logical reference only.
 */
class PartnerDocumentService
{
    public function getDocuments(?string $partnerId = null): Collection
    {
        $query = PartnerDocument::query()->orderBy('created_at', 'desc');

        if ($partnerId) {
            $this->assertPartnerAccessible($partnerId);
            $query->where('partner_id', $partnerId);
        } else {
            $ids = $this->accessiblePartnerIds();
            if ($ids->isEmpty()) {
                return collect();
            }
            $query->whereIn('partner_id', $ids);
        }

        return $query->get();
    }

    public function getDocumentById(string $documentId): PartnerDocument
    {
        $document = PartnerDocument::query()
            ->where('partner_document_id', $documentId)
            ->firstOrFail();

        $this->assertPartnerAccessible($document->partner_id);

        return $document;
    }

    public function createDocument(CreatePartnerDocumentDTO $dto): PartnerDocument
    {
        $this->assertPartnerAccessible($dto->partnerId);

        return PartnerDocument::create([
            'partner_id'      => $dto->partnerId,
            'document_type'   => $dto->documentType,
            'document_number' => $dto->documentNumber,
            'storage_path'    => $dto->storagePath,
            'status'          => $dto->status,
            'verified_at'     => $dto->verifiedAt,
            'verified_by'     => $dto->verifiedBy,
        ]);
    }

    public function updateDocument(string $documentId, UpdatePartnerDocumentDTO $dto): PartnerDocument
    {
        $document = $this->getDocumentById($documentId);

        $document->update([
            'document_type'   => $dto->documentType ?? $document->document_type,
            'document_number' => array_key_exists('document_number', $dto->raw)
                ? $dto->documentNumber
                : $document->document_number,
            'storage_path'    => $dto->storagePath ?? $document->storage_path,
            'status'          => $dto->status ?? $document->status,
            'verified_at'     => array_key_exists('verified_at', $dto->raw)
                ? $dto->verifiedAt
                : $document->verified_at,
            'verified_by'     => array_key_exists('verified_by', $dto->raw)
                ? $dto->verifiedBy
                : $document->verified_by,
        ]);

        return $document->fresh();
    }

    public function deleteDocument(string $documentId): void
    {
        $document = $this->getDocumentById($documentId);
        $document->delete();
    }

    private function assertPartnerAccessible(string $partnerId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $query = Partner::query()->where('partner_id', $partnerId);

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }

        if (!$query->exists()) {
            throw new Exception('Partner not found or not accessible in this context.');
        }
    }

    private function accessiblePartnerIds(): Collection
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $query = Partner::query()->select('partner_id');

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }

        return $query->pluck('partner_id');
    }
}
