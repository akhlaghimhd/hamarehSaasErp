<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\ScopeScoped;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * erp_branches — Organization core (Owner: Organization / Layer 5)
 * SoftDeletes + full audit fields required by Architecture Rules 1.4 & 3.5
 */
class Branch extends Model
{
    use HasUuids, TenantScoped, ScopeScoped, SoftDeletes;

    protected $table = 'erp_branches';

    protected $primaryKey = 'branch_id';

    /**
     * Scope type and column for Resource-level filtering (Law 4.2 / 4.3)
     */
    protected static string $scopeType = 'BRANCH';
    protected static string $scopeColumn = 'branch_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'code',
        'name',
        'address',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function departments()
    {
        return $this->hasMany(Department::class, 'branch_id', 'branch_id');
    }
}
