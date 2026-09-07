<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Inventory\Services\InventoryDocumentService;
use App\Modules\Workflow\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * L6-INV-19 – When workflow completes for inventory documents, apply domain transition.
 * - inventory_documents: approve → post (Posted); reject → Draft
 */
class WorkflowTaskCompletedListener
{
    public function __construct(
        private readonly InventoryDocumentService $documentService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        try {
            $aggregateType = $payload['target_aggregate_type'] ?? null;
            $aggregateId = $payload['target_aggregate_id'] ?? null;
            $approved = (bool) ($payload['approved'] ?? false);
            $instanceStatus = (int) ($payload['instance_status'] ?? 0);

            if ($aggregateType !== 'inventory_documents' || empty($aggregateId)) {
                return;
            }

            if (!in_array($instanceStatus, [
                WorkflowEngineService::INSTANCE_COMPLETED,
                WorkflowEngineService::INSTANCE_TERMINATED,
            ], true)) {
                return;
            }

            if ($approved && $instanceStatus === WorkflowEngineService::INSTANCE_COMPLETED) {
                $this->documentService->approveFromWorkflow($aggregateId);
                Log::info('Inventory document posted via WorkflowTaskCompletedV1', [
                    'document_id' => $aggregateId,
                ]);

                return;
            }

            if (!$approved) {
                $this->documentService->rejectFromWorkflow($aggregateId);
                Log::info('Inventory document returned to draft after workflow rejection', [
                    'document_id' => $aggregateId,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Inventory WorkflowTaskCompletedListener failed: ' . $e->getMessage(), [
                'payload' => $payload,
            ]);
            throw $e;
        }
    }
}
