<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Bom extends Model
{
    use TenantScoped, HasUuids;

    protected $table = 'mfg_boms';
    protected $primaryKey = 'bom_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'item_id',
        'bom_code',
        'bom_version',
        'is_active',
        'total_standard_cost'
    ];

    public function items()
    {
        return $this->hasMany(BomItem::class, 'bom_id', 'bom_id');
    }
}