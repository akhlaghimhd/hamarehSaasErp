<?php

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;
use App\Base\Traits\ScopeScoped;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasUuids, TenantScoped, ScopeScoped, SoftDeletes;

    protected $table = 'erp_departments';
    protected $primaryKey = 'department_id';

    /**
     * Scope type and column for Resource-level filtering (Law 4.2 / 4.3)
     */
    protected static string $scopeType = 'DEPARTMENT';
    protected static string $scopeColumn = 'department_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'parent_department_id',
        'code',
        'name',
        'manager_user_id',
        'is_active',
        'row_version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_department_id', 'department_id');
    }

    public function children()
    {
        return $this->hasMany(Department::class, 'parent_department_id', 'department_id');
    }
}