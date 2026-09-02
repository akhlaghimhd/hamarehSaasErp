<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

class Bom extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'mfg_boms';
    protected $primaryKey = 'bom_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'item_id',
        'bom_code',
        'bom_version',
        'is_active',
        'total_standard_cost',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'total_standard_cost' => 'decimal:4',
        'row_version' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(BomItem::class, 'bom_id', 'bom_id');
    }
}
