<?php

namespace App\Modules\Inventory\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * inv_stock_batches — lot/batch tracking (Owner: Inventory)
 * Per Inventory_Logistics_Module.md
 * qc_status: 1 Pending, 2 Approved, 3 Quarantined
 */
class StockBatch extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'inv_stock_batches';

    protected $primaryKey = 'batch_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'item_id',
        'batch_number',
        'quantity_produced',
        'quantity_remaining',
        'production_date',
        'expiration_date',
        'qc_status',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'quantity_produced'  => 'decimal:4',
        'quantity_remaining' => 'decimal:4',
        'production_date'    => 'date',
        'expiration_date'    => 'date',
        'qc_status'          => 'integer',
        'row_version'        => 'integer',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
        'deleted_at'         => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }
}
