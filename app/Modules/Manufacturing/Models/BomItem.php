<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Base\Traits\TenantScoped;

/**
 * mfg_bom_items — material_item_id is logical reference to inv_items.
 */
class BomItem extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'mfg_bom_items';
    protected $primaryKey = 'bom_item_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'bom_id',
        'material_item_id',
        'quantity',
        'scrap_percentage',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'quantity'         => 'decimal:4',
        'scrap_percentage' => 'decimal:4',
        'row_version'      => 'integer',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'bom_id', 'bom_id');
    }
}
