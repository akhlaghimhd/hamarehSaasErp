<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BomItem extends Model
{
    use TenantScoped, HasUuids;

    protected $table = 'mfg_bom_items';
    protected $primaryKey = 'bom_item_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'bom_id',
        'raw_material_item_id',
        'quantity',
        'unit_of_measure',
        'scrap_percentage'
    ];

    public function bom()
    {
        return $this->belongsTo(Bom::class, 'bom_id', 'bom_id');
    }
}