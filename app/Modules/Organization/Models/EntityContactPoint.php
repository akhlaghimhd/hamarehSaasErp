<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * entity_contact_points — polymorphic contact surface
 * contact_point_type: 1=Phone, 2=Mobile, 3=Email, 4=Website
 * Owner: Organization / Layer 5  |  ORG-P0-08
 */
class EntityContactPoint extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'entity_contact_points';

    protected $primaryKey = 'entity_contact_point_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'contact_point_type',
        'contact_value',
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
            'contact_point_type' => 'integer',
            'is_primary'         => 'boolean',
            'status'             => 'integer',
            'row_version'        => 'integer',
            'created_at'         => 'datetime',
            'updated_at'         => 'datetime',
            'deleted_at'         => 'datetime',
        ];
    }
}
