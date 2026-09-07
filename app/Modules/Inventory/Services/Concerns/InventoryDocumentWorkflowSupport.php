<?php

namespace App\Modules\Inventory\Services\Concerns;

use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Workflow\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-INV-19 – Workflow approval helpers for Inventory Documents.
 * Draft → Pending Approval → Posted (approve) / Draft (reject).
 */
trait InventoryDocumentWorkflowSupport
{
    public function submitForApproval(
        string $id,
        string $definitionCode = 'INVENTORY_DOCUMENT_APPROVAL_V1'
    ): InventoryDocument {
        try {
            return DB::transaction(function () use ($id, $definitionCode) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $document = InventoryDocument::query()->lockForUpdate()->find($id);
                if (!$document) {
                    throw new NotFoundHttpException('Inventory document not found.');
                }
                if ((int) $document->status !== self::STATUS_DRAFT) {
                    throw new ConflictHttpException('Only draft inventory documents can be submitted for approval.');
                }
                if ($document->items()->count() === 0) {
                    throw new ConflictHttpException('Cannot submit an inventory document with no lines.');
                }

                $engine = app(WorkflowEngineService::class);
                $engine->startInstance(
                    definitionCode: $definitionCode,
                    targetAggregateType: 'inventory_documents',
                    targetAggregateId: $document->document_id,
                    contextSnapshot: [
                        'document_number' => $document->document_number,
                        'document_type'   => (int) $document->document_type,
                        'posting_date'    => optional($document->posting_date)?->toDateString(),
                    ],
                );

                $document->update([
                    'status'      => self::STATUS_PENDING,
                    'updated_by'  => $userId,
                    'row_version' => ((int) ($document->row_version ?? 1)) + 1,
                ]);

                return $document->fresh(['items']);
            });
        } catch (Exception $e) {
            Log::error('Failed to submit InventoryDocument for approval: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Workflow approve → post document (stock + accounting).
     * Called only from PENDING status.
     */
    public function approveFromWorkflow(string $id): InventoryDocument
    {
        try {
            return DB::transaction(function () use ($id) {
                $document = InventoryDocument::with('items')->lockForUpdate()->find($id);
                if (!$document) {
                    throw new NotFoundHttpException('Inventory document not found.');
                }
                if ((int) $document->status !== self::STATUS_PENDING) {
                    throw new ConflictHttpException('Only pending-approval inventory documents can be approved from workflow.');
                }

                // Transition to draft momentarily so existing postDocument guard accepts it,
                // then run full post (stock movements + accounting + outbox).
                $document->update([
                    'status'      => self::STATUS_DRAFT,
                    'row_version' => ((int) ($document->row_version ?? 1)) + 1,
                ]);

                return $this->postDocument($document->document_id);
            });
        } catch (Exception $e) {
            Log::error('Failed to approve InventoryDocument from workflow: ' . $e->getMessage());
            throw $e;
        }
    }

    public function rejectFromWorkflow(string $id): InventoryDocument
    {
        $document = InventoryDocument::query()->lockForUpdate()->find($id);
        if (!$document) {
            throw new NotFoundHttpException('Inventory document not found.');
        }
        if ((int) $document->status !== self::STATUS_PENDING) {
            throw new ConflictHttpException('Only pending-approval inventory documents can be rejected from workflow.');
        }

        $userId = Context::get('user_id');
        $document->update([
            'status'      => self::STATUS_DRAFT,
            'updated_by'  => $userId,
            'row_version' => ((int) ($document->row_version ?? 1)) + 1,
        ]);

        return $document->fresh(['items']);
    }
}
