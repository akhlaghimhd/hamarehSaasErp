<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrgHierarchy extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    public const PURPOSE_LEGAL = 'LEGAL';
    public const PURPOSE_MANAGEMENT = 'MANAGEMENT';
    public const PURPOSE_TAX = 'TAX';
    public const PURPOSE_ESTABLISHMENT = 'ESTABLISHMENT';
    public const PURPOSE_CUSTOM = 'CUSTOM';

    public const PURPOSES = [
        self::PURPOSE_LEGAL,
        self::PURPOSE_MANAGEMENT,
        self::PURPOSE_TAX,
        self::PURPOSE_ESTABLISHMENT,
        self::PURPOSE_CUSTOM,
    ];

    protected $table = 'erp_org_hierarchies';

    protected $primaryKey = 'hierarchy_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'purpose',
        'version',
        'valid_from',
        'valid_to',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'version'     => 'integer',
            'is_active'   => 'boolean',
            'valid_from'  => 'date',
            'valid_to'    => 'date',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }

    public function nodes()
    {
        return $this->hasMany(OrgHierarchyNode::class, 'hierarchy_id', 'hierarchy_id');
    }
}
