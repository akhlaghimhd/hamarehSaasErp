<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

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
        'raw_material_item_id',
        'quantity',
        'unit_of_measure',
        'scrap_percentage',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'scrap_percentage' => 'decimal:2',
        'row_version' => 'integer',
    ];

    public function bom()
    {
        return $this->belongsTo(Bom::class, 'bom_id', 'bom_id');
    }
}
