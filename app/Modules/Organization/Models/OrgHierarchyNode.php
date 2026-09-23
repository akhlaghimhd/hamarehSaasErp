<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrgHierarchyNode extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    public const ENTITY_TYPES = [
        'COMPANY',
        'BRANCH',
        'DEPARTMENT',
        'BUSINESS_UNIT',
        'COST_CENTER',
    ];

    protected $table = 'erp_org_hierarchy_nodes';

    protected $primaryKey = 'node_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'hierarchy_id',
        'parent_node_id',
        'entity_type',
        'entity_id',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'sort_order'  => 'integer',
            'is_active'   => 'boolean',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }

    public function hierarchy()
    {
        return $this->belongsTo(OrgHierarchy::class, 'hierarchy_id', 'hierarchy_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_node_id', 'node_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_node_id', 'node_id');
    }
}
