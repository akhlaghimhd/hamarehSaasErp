<?php

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'erp_branches';
    protected $primaryKey = 'branch_id';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'code',
        'name',
        'address',
        'is_active',
        'row_version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function departments()
    {
        return $this->hasMany(Department::class, 'branch_id', 'branch_id');
    }
}