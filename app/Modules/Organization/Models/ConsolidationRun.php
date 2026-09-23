<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsolidationRun extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SNAPSHOTTED = 'SNAPSHOTTED';
    public const STATUS_POSTED = 'POSTED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'erp_consolidation_runs';

    protected $primaryKey = 'consol_run_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'hierarchy_id',
        'code',
        'name',
        'period_start',
        'period_end',
        'rate_set_ref',
        'status',
        'snapshot_payload',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'period_start'     => 'date',
            'period_end'       => 'date',
            'snapshot_payload' => 'array',
            'row_version'      => 'integer',
            'created_at'       => 'datetime',
            'updated_at'       => 'datetime',
            'deleted_at'       => 'datetime',
        ];
    }
}
