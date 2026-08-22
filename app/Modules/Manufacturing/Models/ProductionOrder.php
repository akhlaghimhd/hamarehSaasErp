<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrder extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'mfg_production_orders';
    
    protected $primaryKey = 'production_order_id';

    protected $fillable = [
        'tenant_id',
        'order_number',
        'item_id', // Logical Reference to MasterData (inv_items)
        'bom_id',  // Physical FK to mfg_boms
        'planned_quantity',
        'produced_quantity',
        'start_date',
        'end_date',
        'status', // 1: Draft, 2: Released, 3: In Progress, 4: Completed, 5: Cancelled
        'row_version',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:4',
        'produced_quantity' => 'decimal:4',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'status' => 'integer',
        'row_version' => 'integer',
    ];

    /**
     * ارتباط با سربرگ فرمولاسیون (BOM)
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'bom_id', 'bom_id');
    }
}