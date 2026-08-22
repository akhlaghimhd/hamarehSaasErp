<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\QualityInspection;
use App\Modules\Manufacturing\DTOs\QualityInspectionDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QualityInspectionService
{
    public function recordInspection(QualityInspectionDTO $dto): QualityInspection
    {
        return DB::transaction(function () use ($dto) {
            
            $inspection = QualityInspection::create([
                'inspection_type' => $dto->inspectionType,
                'source_document_type' => $dto->sourceDocumentType,
                'source_document_id' => $dto->sourceDocumentId,
                'item_id' => $dto->itemId,
                'batch_id' => $dto->batchId,
                'inspection_number' => $dto->inspectionNumber,
                'inspection_date' => $dto->inspectionDate,
                'inspector_user_id' => $dto->inspectorUserId,
                'sample_quantity' => $dto->sampleQuantity,
                'accepted_quantity' => $dto->acceptedQuantity,
                'rejected_quantity' => $dto->rejectedQuantity,
                'qc_status' => $dto->qcStatus,
                'notes' => $dto->notes,
                'row_version' => 1,
            ]);

            // شلیک رویداد بازرسی کیفی به سمت Outbox
            // ماژول انبار این را می‌شنود تا قرنطینه یا ضایعات را مدیریت کند
            DB::table('event_outbox')->insert([
                'event_id' => Str::uuid(),
                'tenant_id' => $inspection->tenant_id ?? DB::raw('current_setting(\'app.current_tenant_id\')::uuid'),
                'aggregate_type' => 'mfg_quality_inspections', 
                'aggregate_id' => $inspection->inspection_id,
                'event_type' => 'manufacturing.quality_inspection.recorded',
                'payload' => json_encode($inspection->toArray()),
                'status' => 1,
                'retry_count' => 0,
                'created_at' => now(),
            ]);

            return $inspection;
        });
    }
}