<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\DTOs\CreateInventoryDocumentDTO;
use App\Modules\Inventory\DTOs\UpdateInventoryDocumentDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InventoryDocumentService
{
    public const STATUS_DRAFT = 1;
    public const STATUS_PENDING = 2;
    public const STATUS_POSTED = 3;
    public const STATUS_VOIDED = 4;

    public const TYPE_RECEIPT = 1;
    public const TYPE_ISSUE = 2;
    public const TYPE_TRANSFER = 3;
    public const TYPE_ADJUSTMENT = 4;

    public function getAllDocuments(?int $documentType = null, ?int $status = null): Collection
    {
        $query = InventoryDocument::query()->with('items');

        if ($documentType !== null) {
            $query->where('document_type', $documentType);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('posting_date')->orderByDesc('created_at')->get();
    }

    public function getDocumentById(string $id): InventoryDocument
    {
        return InventoryDocument::with('items')->findOrFail($id);
    }

    public function createDocument(CreateInventoryDocumentDTO $dto): InventoryDocument
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');

                // New documents start as Draft unless explicitly Pending (approval workflow later)
                $status = in_array($dto->status, [self::STATUS_DRAFT, self::STATUS_PENDING], true)
                    ? $dto->status
                    : self::STATUS_DRAFT;

                $document = InventoryDocument::create([
                    'tenant_id'            => $tenantId,
                    'fiscal_period_id'     => $dto->fiscal_period_id,
                    'document_type'        => $dto->document_type,
                    'document_number'      => $dto->document_number,
                    'posting_date'         => $dto->posting_date ?? now(),
                    'source_document_type' => $dto->source_document_type,
                    'source_document_id'   => $dto->source_document_id,
                    'business_partner_id'  => $dto->business_partner_id,
                    'status'               => $status,
                    'description'          => $dto->description,
                    'created_by'           => Context::get('user_id'),
                    'row_version'          => 1,
                ]);

                $this->dispatchOutboxEvent('inventory.document.created.v1', $document, $tenantId);

                return $document->load('items');
            });
        } catch (Exception $e) {
            Log::error('Failed to create InventoryDocument: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateDocument(string $id, UpdateInventoryDocumentDTO $dto): InventoryDocument
    {
        try {
            return DB::transaction(function () use ($id, $dto) {
                $document = InventoryDocument::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                if ((int) $document->status !== self::STATUS_DRAFT) {
                    throw new ConflictHttpException('Only draft inventory documents can be updated.');
                }

                $updateData = array_filter([
                    'posting_date' => $dto->posting_date,
                    'description'  => $dto->description,
                    'status'       => $dto->status !== null && in_array($dto->status, [self::STATUS_DRAFT, self::STATUS_PENDING], true)
                        ? $dto->status
                        : null,
                    'updated_by'   => Context::get('user_id'),
                ], fn ($value) => !is_null($value));

                if ($dto->clear_business_partner) {
                    $updateData['business_partner_id'] = null;
                } elseif ($dto->business_partner_id !== null) {
                    $updateData['business_partner_id'] = $dto->business_partner_id;
                }

                if ($dto->clear_source) {
                    $updateData['source_document_id'] = null;
                    $updateData['source_document_type'] = null;
                } else {
                    if ($dto->source_document_id !== null) {
                        $updateData['source_document_id'] = $dto->source_document_id;
                    }
                    if ($dto->source_document_type !== null) {
                        $updateData['source_document_type'] = $dto->source_document_type;
                    }
                }

                $updateData['row_version'] = ((int) ($document->row_version ?? 1)) + 1;

                $document->update($updateData);

                $this->dispatchOutboxEvent('inventory.document.updated.v1', $document, $tenantId);

                return $document->fresh(['items']);
            });
        } catch (Exception $e) {
            Log::error('Failed to update InventoryDocument: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteDocument(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $document = InventoryDocument::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                if ((int) $document->status !== self::STATUS_DRAFT) {
                    throw new ConflictHttpException('Only draft inventory documents can be deleted.');
                }

                $document->update(['deleted_by' => Context::get('user_id')]);
                $document->delete();

                $this->dispatchOutboxEvent('inventory.document.deleted.v1', $document, $tenantId);
            });
        } catch (Exception $e) {
            Log::error('Failed to delete InventoryDocument: ' . $e->getMessage());
            throw $e;
        }
    }

    private function dispatchOutboxEvent(string $eventType, InventoryDocument $document, string $tenantId): void
    {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => 'inv_documents',
            'aggregate_id'   => $document->document_id,
            'event_type'     => $eventType,
            'payload'        => json_encode([
                'document_id'     => $document->document_id,
                'document_number' => $document->document_number,
                'document_type'   => $document->document_type,
                'status'          => $document->status,
            ]),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
