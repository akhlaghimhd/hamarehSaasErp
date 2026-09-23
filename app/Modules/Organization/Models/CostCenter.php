<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostCenter extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'erp_cost_centers';

    protected $primaryKey = 'cost_center_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'department_id',
        'parent_cost_center_id',
        'code',
        'name',
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

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_cost_center_id', 'cost_center_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_cost_center_id', 'cost_center_id');
    }
}
