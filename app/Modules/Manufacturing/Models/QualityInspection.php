<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

class QualityInspection extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'mfg_quality_inspections';
    
    protected $primaryKey = 'inspection_id';

    protected $fillable = [
        'tenant_id',
        'inspection_type', // 1: Incoming, 2: Production Output, 3: Final Product
        'source_document_type', // e.g., PRODUCTION_ORDER, PURCHASE_RECEIPT
        'source_document_id', // Logical Reference
        'item_id', // Logical Reference to inv_items
        'batch_id', // Logical Reference to inv_stock_batches
        'inspection_number',
        'inspection_date',
        'inspector_user_id', // Logical Reference to Identity layer
        'sample_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'qc_status', // 1: Pending, 2: Approved, 3: Rejected, 4: Quarantine
        'notes',
        'row_version',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'inspection_type' => 'integer',
        'inspection_date' => 'datetime',
        'sample_quantity' => 'decimal:4',
        'accepted_quantity' => 'decimal:4',
        'rejected_quantity' => 'decimal:4',
        'qc_status' => 'integer',
        'row_version' => 'integer',
    ];
}