<?php

namespace App\Modules\MasterData\Models;

use App\Base\Models\BaseModel; // فرض بر این است که یک مدل پایه برای UUID و غیره دارید
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

class CostCenter extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'cost_centers';
    
    // کلید اصلی از نوع UUID
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
    ];

    /**
     * ارتباط با مرکز هزینه پدر (Self-referencing)
     */
    public function parent()
    {
        return $this->belongsTo(CostCenter::class, 'parent_cost_center_id');
    }

    /**
     * ارتباط با مراکز هزینه فرزند (Self-referencing)
     */
    public function children()
    {
        return $this->hasMany(CostCenter::class, 'parent_cost_center_id');
    }
}