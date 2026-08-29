<?php

namespace App\Modules\PartnerLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PartnerLayer core entity (Layer 3 SSOT).
 *
 * tenant_id is nullable (platform-level partners). Do not apply TenantScoped
 * globally — filtering is explicit when a tenant context applies.
 */
class Partner extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'partners';
    protected $primaryKey = 'partner_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'parent_partner_id',
        'parent_path',
        'code',
        'name',
        'partner_type',
        'ownership_type',
        'commission_enabled',
        'phone',
        'email',
        'address',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'commission_enabled' => 'boolean',
        'partner_type'       => 'integer',
        'ownership_type'     => 'integer',
        'status'             => 'integer',
        'row_version'        => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_partner_id', 'partner_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_partner_id', 'partner_id');
    }

    public function partnerUsers(): HasMany
    {
        return $this->hasMany(PartnerUser::class, 'partner_id', 'partner_id');
    }

    public function tenantAssignments(): HasMany
    {
        return $this->hasMany(PartnerTenantAssignment::class, 'partner_id', 'partner_id');
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(PartnerAgreement::class, 'partner_id', 'partner_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(PartnerCommission::class, 'partner_id', 'partner_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(PartnerPayout::class, 'partner_id', 'partner_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PartnerContact::class, 'partner_id', 'partner_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PartnerDocument::class, 'partner_id', 'partner_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(PartnerBankAccount::class, 'partner_id', 'partner_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(PartnerActivityLog::class, 'partner_id', 'partner_id');
    }
}
