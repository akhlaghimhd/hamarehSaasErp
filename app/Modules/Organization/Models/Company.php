<?php

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'erp_companies';
    protected $primaryKey = 'company_id';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'registration_number',
        'economic_code',
        'is_active',
        'row_version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class, 'company_id', 'company_id');
    }
}