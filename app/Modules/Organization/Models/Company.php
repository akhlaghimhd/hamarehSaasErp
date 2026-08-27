<?php

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;
use App\Base\Traits\ScopeScoped;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasUuids, TenantScoped, ScopeScoped, SoftDeletes;

    protected $table = 'erp_companies';
    protected $primaryKey = 'company_id';

    /**
     * Scope type and column for Resource-level filtering (Law 4.2 / 4.3)
     */
    protected static string $scopeType = 'COMPANY';
    protected static string $scopeColumn = 'company_id';

    public $incrementing = false;
    protected $keyType = 'string';

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