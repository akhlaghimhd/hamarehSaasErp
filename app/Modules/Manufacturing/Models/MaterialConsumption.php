<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Base\Traits\TenantScoped;

/**
 * mfg_material_consumptions
 * status: 1 Registered, 2 Posted, 0 Cancelled
 * variance_quantity is generated/stored in DB — do not mass-assign.
 */
class MaterialConsumption extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'mfg_material_consumptions';
    protected $primaryKey = 'consumption_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'bom_item_id',
        'material_item_id',
        'inventory_document_id',
        'planned_quantity',
        'actual_quantity',
        'consumption_date',
        'consumed_by',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'planned_quantity'  => 'decimal:4',
        'actual_quantity'   => 'decimal:4',
        'variance_quantity' => 'decimal:4',
        'consumption_date'  => 'datetime',
        'status'            => 'integer',
        'row_version'       => 'integer',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id', 'production_order_id');
    }

    public function bomItem(): BelongsTo
    {
        return $this->belongsTo(BomItem::class, 'bom_item_id', 'bom_item_id');
    }
}
