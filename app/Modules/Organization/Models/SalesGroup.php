<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesGroup extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'erp_sales_groups';

    protected $primaryKey = 'sales_group_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'sales_office_id', 'code', 'name', 'is_active',
        'created_by', 'updated_by', 'deleted_by', 'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'row_version' => 'integer',
            'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime',
        ];
    }

    public function office()
    {
        return $this->belongsTo(SalesOffice::class, 'sales_office_id', 'sales_office_id');
    }
}
