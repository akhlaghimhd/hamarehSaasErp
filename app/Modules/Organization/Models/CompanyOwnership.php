<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * erp_company_ownerships — ORG-P1-04
 * Owner module: Organization
 */
class CompanyOwnership extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'erp_company_ownerships';

    protected $primaryKey = 'ownership_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'owner_company_id',
        'ownership_percent',
        'relation_type',
        'valid_from',
        'valid_to',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'ownership_percent' => 'decimal:4',
            'status'            => 'integer',
            'valid_from'        => 'date',
            'valid_to'          => 'date',
            'row_version'       => 'integer',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
            'deleted_at'        => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function ownerCompany()
    {
        return $this->belongsTo(Company::class, 'owner_company_id', 'company_id');
    }
}
