<?php

namespace App\Modules\MasterData\Models;

use App\Base\Traits\TenantScoped;
use App\Base\Traits\ScopeScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasUuids, HasFactory, TenantScoped, ScopeScoped, SoftDeletes;

    protected $table = 'warehouses';

    // طبق قانون ۳.۴: نام Primary Key باید نام مفرد + _id باشد
    protected $primaryKey = 'warehouse_id';

    /**
     * Scope type and column for Resource-level filtering (Law 4.2 / 4.3)
     */
    protected static string $scopeType = 'WAREHOUSE';
    protected static string $scopeColumn = 'warehouse_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'location',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Modules\SaasPlatform\Models\Tenant::class, 'tenant_id', 'tenant_id');
    }
}
