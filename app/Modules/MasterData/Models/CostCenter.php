<?php

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;
use App\Base\Traits\ScopeScoped;

class CostCenter extends Model
{
    use HasUuids, SoftDeletes, TenantScoped, ScopeScoped;

    protected $table = 'cost_centers';
    protected $primaryKey = 'cost_center_id';

    /**
     * Scope type and column for Resource-level filtering (Law 4.2 / 4.3)
     */
    protected static string $scopeType = 'COST_CENTER';
    protected static string $scopeColumn = 'cost_center_id';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'department_id',
        'parent_cost_center_id',
        'code',
        'name',
        'type',    // 1:Company, 2:Branch, 3:Department, 4:CostCenter, 5:Project
        'status',  // 1: Active, 0: Inactive
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    /**
     * ارتباط با مرکز هزینه پدر (Self-referencing)
     */
    public function parent()
    {
        return $this->belongsTo(CostCenter::class, 'parent_cost_center_id', 'cost_center_id');
    }

    /**
     * ارتباط با مراکز هزینه فرزند (Self-referencing)
     */
    public function children()
    {
        return $this->hasMany(CostCenter::class, 'parent_cost_center_id', 'cost_center_id');
    }
}