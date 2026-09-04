<?php

namespace App\Modules\Inventory\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * inv_items — Inventory Master Data (Owner: Inventory module)
 * Per Inventory_Logistics_Module.md
 */
class Item extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'inv_items';

    protected $primaryKey = 'item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'item_group_id',
        'uom_id',
        'code',
        'name',
        'description',
        'item_type',
        'valuation_method',
        'extra_attributes',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'item_type'         => 'integer',
        'valuation_method'  => 'integer',
        'status'            => 'integer',
        'extra_attributes'  => 'array',
        'row_version'       => 'integer',
    ];
}
