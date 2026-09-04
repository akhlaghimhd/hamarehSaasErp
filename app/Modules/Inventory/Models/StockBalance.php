<?php

namespace App\Modules\Inventory\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * inv_stock_balances — live stock ledger (Owner: Inventory)
 * Per Inventory_Logistics_Module.md
 * No SoftDeletes — live balance rows are updated, not deleted.
 * quantity_available is a PostgreSQL GENERATED column (read-only).
 */
class StockBalance extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'inv_stock_balances';

    protected $primaryKey = 'stock_balance_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'location_id',
        'item_id',
        'quantity_on_hand',
        'quantity_reserved',
        'row_version',
    ];

    protected $casts = [
        'quantity_on_hand'   => 'decimal:4',
        'quantity_reserved'  => 'decimal:4',
        'quantity_available' => 'decimal:4',
        'row_version'        => 'integer',
        'updated_at'         => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'warehouse_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }
}
