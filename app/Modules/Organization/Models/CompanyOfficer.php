<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyOfficer extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'erp_company_officers';

    protected $primaryKey = 'officer_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'role_code',
        'role_title',
        'full_name',
        'person_user_id',
        'national_id',
        'mandate_from',
        'mandate_to',
        'has_signing_authority',
        'mandate_notes',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'has_signing_authority' => 'boolean',
            'is_active'             => 'boolean',
            'mandate_from'          => 'date',
            'mandate_to'            => 'date',
            'row_version'           => 'integer',
            'created_at'            => 'datetime',
            'updated_at'            => 'datetime',
            'deleted_at'            => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
}
