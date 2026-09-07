<?php

namespace App\Modules\Inventory\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostLayer extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'inv_cost_layers';
    protected $primaryKey = 'cost_layer_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'item_id',
        'location_id',
        'quantity_remaining',
        'unit_cost',
        'received_at',
        'source_document_id',
        'source_document_item_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'quantity_remaining' => 'decimal:4',
        'unit_cost'          => 'decimal:4',
        'received_at'        => 'datetime',
        'row_version'        => 'integer',
    ];
}
