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

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'inspection_type',
        'source_document_type',
        'source_document_id',
        'item_id',
        'batch_id',
        'inspection_number',
        'inspection_date',
        'inspector_user_id',
        'sample_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'qc_status',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
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
