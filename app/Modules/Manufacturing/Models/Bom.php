<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Base\Traits\TenantScoped;

/**
 * mfg_boms — aligned to migration columns (version_code, title, status).
 * status: 1 Draft, 2 Approved, 3 Obsolete
 */
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
        'version_code',
        'title',
        'is_active',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'status'      => 'integer',
        'row_version' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class, 'bom_id', 'bom_id');
    }
}
