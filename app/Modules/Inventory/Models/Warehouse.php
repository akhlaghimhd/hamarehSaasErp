<?php

namespace App\Modules\Inventory\Models;

use App\Base\Traits\TenantScoped;
use App\Base\Traits\ScopeScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * inv_warehouses — Inventory Master Data (Owner: Inventory module)
 * Per Inventory_Logistics_Module.md
 * Scope type WAREHOUSE for Resource-level filtering (Law 4.2 / 4.3)
 */
class Warehouse extends Model
{
    use HasUuids, HasFactory, TenantScoped, ScopeScoped, SoftDeletes;

    protected $table = 'inv_warehouses';

    protected $primaryKey = 'warehouse_id';

    protected static string $scopeType = 'WAREHOUSE';
    protected static string $scopeColumn = 'warehouse_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'code',
        'name',
        'is_bonded',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_bonded'  => 'boolean',
            'status'     => 'integer',
            'row_version'=> 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
