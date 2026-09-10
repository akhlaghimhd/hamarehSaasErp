<?php

namespace App\Modules\MasterData\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessPartner extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'business_partners';

    protected $primaryKey = 'business_partner_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'code',
        'display_name',
        'partner_type', // 1: Individual, 2: Organization
        'status',       // 1: Active, 2: Suspended, 3: Blocked
        'parent_business_partner_id',
        'credit_limit',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'partner_type' => 'integer',
        'status' => 'integer',
        'credit_limit' => 'decimal:4',
    ];

    /**
     * Self-referencing relationship for hierarchical partners (e.g., holding companies)
     */
    public function parent()
    {
        return $this->belongsTo(BusinessPartner::class, 'parent_business_partner_id', 'business_partner_id');
    }

    public function children()
    {
        return $this->hasMany(BusinessPartner::class, 'parent_business_partner_id', 'business_partner_id');
    }

    public function person(): HasOne
    {
        return $this->hasOne(Person::class, 'business_partner_id', 'business_partner_id');
    }

    public function organization(): HasOne
    {
        return $this->hasOne(BusinessPartnerOrganization::class, 'business_partner_id', 'business_partner_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(BusinessPartnerRole::class, 'business_partner_id', 'business_partner_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(BusinessPartnerContact::class, 'business_partner_id', 'business_partner_id');
    }

    public function identifications(): HasMany
    {
        return $this->hasMany(BusinessPartnerIdentification::class, 'business_partner_id', 'business_partner_id');
    }
}
