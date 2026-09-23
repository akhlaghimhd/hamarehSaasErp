<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\ScopeScoped;
use App\Base\Traits\TenantScoped;
use App\Modules\MasterData\Models\EntityAddress;
use App\Modules\MasterData\Models\EntityContactPoint;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * erp_companies — Organization core (Owner: Organization / Layer 5)
 * SoftDeletes + full audit fields required by Architecture Rules 1.4 & 3.5
 *
 * P0 enrichment: legal_name, trade_name, company_type, tax_*, status, …
 * P1 group spine: is_primary, parent_company_id, entity_kind (ADR-ORG-01)
 *
 * Address/contact: reuse MasterData polymorphic tables (Law 5.1 SoT).
 */
class Company extends Model
{
    use HasUuids, TenantScoped, ScopeScoped, SoftDeletes;

    public const ENTITY_KIND_OPERATING = 'OPERATING';
    public const ENTITY_KIND_CONSOLIDATION = 'CONSOLIDATION';
    public const ENTITY_KIND_ELIMINATION = 'ELIMINATION';

    public const ENTITY_KINDS = [
        self::ENTITY_KIND_OPERATING,
        self::ENTITY_KIND_CONSOLIDATION,
        self::ENTITY_KIND_ELIMINATION,
    ];

    protected $table = 'erp_companies';

    protected $primaryKey = 'company_id';

    protected static string $scopeType = 'COMPANY';
    protected static string $scopeColumn = 'company_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'legal_name',
        'trade_name',
        'company_type',
        'registration_number',
        'registration_date',
        'registration_place',
        'incorporation_country_id',
        'economic_code',
        'tax_identifier',
        'national_id',
        'vat_registration',
        'is_active',
        'status',
        'is_primary',
        'parent_company_id',
        'entity_kind',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active'          => 'boolean',
            'is_primary'         => 'boolean',
            'company_type'       => 'integer',
            'status'             => 'integer',
            'registration_date'  => 'date',
            'row_version'        => 'integer',
            'created_at'         => 'datetime',
            'updated_at'         => 'datetime',
            'deleted_at'         => 'datetime',
        ];
    }

    /**
     * Domain defaults for NOT NULL / spine columns when callers omit them
     * (tests, seeders, legacy create paths). Does not drop constraints.
     */
    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (blank($company->legal_name) && filled($company->name)) {
                $company->legal_name = $company->name;
            }

            if ($company->status === null) {
                $company->status = ($company->is_active === false) ? 2 : 1;
            }

            if ($company->row_version === null) {
                $company->row_version = 1;
            }

            if ($company->is_primary === null) {
                $company->is_primary = false;
            }

            if (blank($company->entity_kind)) {
                $company->entity_kind = self::ENTITY_KIND_OPERATING;
            }
        });
    }

    public function branches()
    {
        return $this->hasMany(Branch::class, 'company_id', 'company_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_company_id', 'company_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_company_id', 'company_id');
    }

    public function ownerships()
    {
        return $this->hasMany(CompanyOwnership::class, 'company_id', 'company_id');
    }

    /** Polymorphic addresses owned by MasterData module */
    public function addresses()
    {
        return $this->hasMany(EntityAddress::class, 'entity_id', 'company_id')
            ->where('entity_type', 'COMPANY');
    }

    /** Polymorphic contact points owned by MasterData module */
    public function contactPoints()
    {
        return $this->hasMany(EntityContactPoint::class, 'entity_id', 'company_id')
            ->where('entity_type', 'COMPANY');
    }
}
