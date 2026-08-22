<?php

namespace App\Modules\Manufacturing\DTOs;

use App\Modules\Manufacturing\Requests\StoreQualityInspectionRequest;

readonly class QualityInspectionDTO
{
    public function __construct(
        public int $inspectionType,
        public ?string $sourceDocumentType,
        public ?string $sourceDocumentId,
        public string $itemId,
        public ?string $batchId,
        public string $inspectionNumber,
        public string $inspectionDate,
        public string $inspectorUserId,
        public float $sampleQuantity,
        public float $acceptedQuantity,
        public float $rejectedQuantity,
        public int $qcStatus,
        public ?string $notes
    ) {}

    public static function fromRequest(StoreQualityInspectionRequest $request): self
    {
        return new self(
            inspectionType: (int) $request->validated('inspection_type'),
            sourceDocumentType: $request->validated('source_document_type'),
            sourceDocumentId: $request->validated('source_document_id'),
            itemId: $request->validated('item_id'),
            batchId: $request->validated('batch_id'),
            inspectionNumber: $request->validated('inspection_number'),
            inspectionDate: $request->validated('inspection_date'),
            inspectorUserId: $request->validated('inspector_user_id'),
            sampleQuantity: (float) $request->validated('sample_quantity'),
            acceptedQuantity: (float) $request->validated('accepted_quantity'),
            rejectedQuantity: (float) $request->validated('rejected_quantity'),
            qcStatus: (int) $request->validated('qc_status'),
            notes: $request->validated('notes')
        );
    }
}