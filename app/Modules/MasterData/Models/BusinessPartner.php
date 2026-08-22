<?php

namespace App\Modules\MasterData\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version'
    ];

    protected $casts = [
        'partner_type' => 'integer',
        'status' => 'integer',
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
}