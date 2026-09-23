<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * entity_addresses — polymorphic address surface (COMPANY, BRANCH, …)
 * Owner: Organization / Layer 5  |  ORG-P0-07
 */
class EntityAddress extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'entity_addresses';

    protected $primaryKey = 'entity_address_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'address_type_id',
        'country_id',
        'province_name',
        'city_name',
        'postal_code',
        'address_text',
        'is_primary',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_primary'  => 'boolean',
            'status'      => 'integer',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }
}
