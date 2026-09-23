<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrganization extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'erp_sales_organizations';

    protected $primaryKey = 'sales_org_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'code', 'name', 'company_id', 'is_active',
        'created_by', 'updated_by', 'deleted_by', 'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'row_version' => 'integer',
            'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime',
        ];
    }

    public function assignments()
    {
        return $this->hasMany(SalesOrgAssignment::class, 'sales_org_id', 'sales_org_id');
    }
}
