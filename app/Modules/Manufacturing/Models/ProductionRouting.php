<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRouting extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'mfg_production_routing';
    protected $primaryKey = 'routing_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'work_center_id',
        'operation_sequence',
        'operation_name',
        'standard_setup_time_hours',
        'standard_run_time_hours',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'operation_sequence' => 'integer',
        'standard_setup_time_hours' => 'decimal:4',
        'standard_run_time_hours' => 'decimal:4',
        'status' => 'integer',
        'row_version' => 'integer',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id', 'production_order_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id', 'work_center_id');
    }
}
