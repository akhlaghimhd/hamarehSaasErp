<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Base\Traits\TenantScoped;

/**
 * mfg_production_orders — status: 1 Draft, 2 Released, 3 In Progress, 4 Completed, 0 Cancelled
 * due_date matches migration (not end_date).
 */
class ProductionOrder extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'mfg_production_orders';
    protected $primaryKey = 'production_order_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'order_number',
        'item_id',
        'bom_id',
        'planned_quantity',
        'produced_quantity',
        'start_date',
        'due_date',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'planned_quantity'  => 'decimal:4',
        'produced_quantity' => 'decimal:4',
        'start_date'        => 'date',
        'due_date'          => 'date',
        'status'            => 'integer',
        'row_version'       => 'integer',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'bom_id', 'bom_id');
    }
}
