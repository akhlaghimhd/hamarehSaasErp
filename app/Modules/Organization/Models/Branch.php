<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\ScopeScoped;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * erp_branches — Organization core
 *
 * P3 plant-grade: branch_kind, parent_branch_id, default_warehouse_id, logistics flags
 */
class Branch extends Model
{
    use HasUuids, TenantScoped, ScopeScoped, SoftDeletes;

    public const KIND_OFFICE = 'OFFICE';
    public const KIND_PLANT = 'PLANT';
    public const KIND_WAREHOUSE_SITE = 'WAREHOUSE_SITE';
    public const KIND_DISTRIBUTION = 'DISTRIBUTION';
    public const KIND_MIXED = 'MIXED';

    public const BRANCH_KINDS = [
        self::KIND_OFFICE,
        self::KIND_PLANT,
        self::KIND_WAREHOUSE_SITE,
        self::KIND_DISTRIBUTION,
        self::KIND_MIXED,
    ];

    protected $table = 'erp_branches';

    protected $primaryKey = 'branch_id';

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
        'branch_kind',
        'parent_branch_id',
        'default_warehouse_id',
        'supports_shipping',
        'supports_receiving',
        'is_manufacturing_site',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active'              => 'boolean',
            'supports_shipping'      => 'boolean',
            'supports_receiving'     => 'boolean',
            'is_manufacturing_site'  => 'boolean',
            'row_version'            => 'integer',
            'created_at'             => 'datetime',
            'updated_at'             => 'datetime',
            'deleted_at'             => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Branch $branch) {
            if (blank($branch->branch_kind)) {
                $branch->branch_kind = self::KIND_OFFICE;
            }
            if ($branch->row_version === null) {
                $branch->row_version = 1;
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_branch_id', 'branch_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_branch_id', 'branch_id');
    }

    public function departments()
    {
        return $this->hasMany(Department::class, 'branch_id', 'branch_id');
    }

    public function warehouseMaps()
    {
        return $this->hasMany(BranchWarehouseMap::class, 'branch_id', 'branch_id');
    }
}
