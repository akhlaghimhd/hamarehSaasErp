<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRouting extends Model
{
    use HasUuids, TenantScoped;

    protected $table = 'mfg_production_routing';
    
    protected $primaryKey = 'routing_id';

    protected $fillable = [
        'tenant_id',
        'production_order_id', // Physical FK
        'work_center_id',      // Physical FK
        'operation_sequence',
        'operation_name',
        'standard_setup_time_hours',
        'standard_run_time_hours',
        'status', // 1: Pending, 2: Active, 3: Completed
        'row_version',
        'created_by',
        'updated_by'
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