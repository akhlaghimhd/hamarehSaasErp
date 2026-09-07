<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\QualityInspection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-MFG-03 — Basic quality inspection register + disposition.
 */
class QualityInspectionService
{
    public const TYPE_INCOMING = 1;
    public const TYPE_PRODUCTION_OUTPUT = 2;
    public const TYPE_FINAL_PRODUCT = 3;

    public const QC_PENDING = 1;
    public const QC_APPROVED = 2;
    public const QC_REJECTED = 3;
    public const QC_QUARANTINE = 4;

    public function list(?int $qcStatus = null): Collection
    {
        $q = QualityInspection::query()->orderByDesc('inspection_date');
        if ($qcStatus !== null) {
            $q->where('qc_status', $qcStatus);
        }

        return $q->get();
    }

    public function getById(string $id): QualityInspection
    {
        $row = QualityInspection::query()->find($id);
        if (!$row) {
            throw new NotFoundHttpException('Quality inspection not found.');
        }

        return $row;
    }

    /**
     * @param  array{inspection_type:int,item_id:string,inspection_number:string,sample_quantity:float,source_document_type?:?string,source_document_id?:?string,batch_id?:?string,notes?:?string}  $data
     */
    public function create(array $data): QualityInspection
    {
        try {
            return DB::transaction(function () use ($data) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId || !$userId) {
                    throw new Exception('Tenant/User Context is missing.');
                }

                return QualityInspection::create([
                    'tenant_id'            => $tenantId,
                    'inspection_type'      => (int) $data['inspection_type'],
                    'source_document_type' => $data['source_document_type'] ?? null,
                    'source_document_id'   => $data['source_document_id'] ?? null,
                    'item_id'              => $data['item_id'],
                    'batch_id'             => $data['batch_id'] ?? null,
                    'inspection_number'    => $data['inspection_number'],
                    'inspection_date'      => now(),
                    'inspector_user_id'    => $userId,
                    'sample_quantity'      => $data['sample_quantity'],
                    'accepted_quantity'    => 0,
                    'rejected_quantity'    => 0,
                    'qc_status'            => self::QC_PENDING,
                    'notes'                => $data['notes'] ?? null,
                    'created_by'           => $userId,
                    'row_version'          => 1,
                ]);
            });
        } catch (Exception $e) {
            Log::error('Failed to create quality inspection: ' . $e->getMessage());
            throw $e;
        }
    }

    public function dispose(
        string $id,
        int $qcStatus,
        float $acceptedQuantity,
        float $rejectedQuantity,
        ?string $notes = null
    ): QualityInspection {
        if (!in_array($qcStatus, [self::QC_APPROVED, self::QC_REJECTED, self::QC_QUARANTINE], true)) {
            throw new ConflictHttpException('Invalid QC disposition status.');
        }

        $row = QualityInspection::query()->lockForUpdate()->find($id);
        if (!$row) {
            throw new NotFoundHttpException('Quality inspection not found.');
        }
        if ((int) $row->qc_status !== self::QC_PENDING) {
            throw new ConflictHttpException('Only pending inspections can be disposed.');
        }

        $sample = (float) $row->sample_quantity;
        if (round($acceptedQuantity + $rejectedQuantity, 4) > round($sample + 1e-9, 4)) {
            throw new ConflictHttpException('Accepted + rejected cannot exceed sample quantity.');
        }

        $row->update([
            'qc_status'         => $qcStatus,
            'accepted_quantity' => $acceptedQuantity,
            'rejected_quantity' => $rejectedQuantity,
            'notes'             => $notes ?? $row->notes,
            'updated_by'        => Context::get('user_id'),
            'row_version'       => ((int) ($row->row_version ?? 1)) + 1,
        ]);

        return $row->fresh();
    }
}
