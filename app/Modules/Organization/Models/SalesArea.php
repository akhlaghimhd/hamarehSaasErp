<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesArea extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'erp_sales_areas';

    protected $primaryKey = 'sales_area_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'sales_org_id', 'distribution_channel_id', 'division_id',
        'code', 'name', 'is_active',
        'created_by', 'updated_by', 'deleted_by', 'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'row_version' => 'integer',
            'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime',
        ];
    }

    public function salesOrganization()
    {
        return $this->belongsTo(SalesOrganization::class, 'sales_org_id', 'sales_org_id');
    }

    public function distributionChannel()
    {
        return $this->belongsTo(DistributionChannel::class, 'distribution_channel_id', 'distribution_channel_id');
    }

    public function productDivision()
    {
        return $this->belongsTo(ProductDivision::class, 'division_id', 'division_id');
    }
}
