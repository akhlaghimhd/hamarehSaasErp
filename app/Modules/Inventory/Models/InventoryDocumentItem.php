<?php

namespace App\Modules\Inventory\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * inv_document_items — document line items (Owner: Inventory)
 * Per Inventory_Logistics_Module.md
 * No SoftDeletes — lines follow parent document lifecycle.
 * total_cost is a PostgreSQL GENERATED column (read-only).
 */
class InventoryDocumentItem extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'inv_document_items';

    protected $primaryKey = 'document_item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    const UPDATED_AT = 'updated_at';
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'tenant_id',
        'document_id',
        'item_id',
        'from_location_id',
        'to_location_id',
        'batch_number',
        'quantity',
        'unit_cost',
        'sort_order',
        'row_version',
    ];

    protected $casts = [
        'quantity'    => 'decimal:4',
        'unit_cost'   => 'decimal:4',
        'total_cost'  => 'decimal:4',
        'sort_order'  => 'integer',
        'row_version' => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'document_id', 'document_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id', 'location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id', 'location_id');
    }
}
