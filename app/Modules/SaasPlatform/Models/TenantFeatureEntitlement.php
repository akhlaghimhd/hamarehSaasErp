<?php

namespace App\Modules\SaasPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

class TenantFeatureEntitlement extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'tenant_feature_entitlements';
    protected $primaryKey = 'entitlement_id';
    public $incrementing = false;
    protected $keyType = 'string';

    public const SOURCE_PLAN = 'PLAN';
    public const SOURCE_ADDON = 'ADDON';
    public const SOURCE_MANUAL = 'MANUAL';
    public const SOURCE_TRIAL = 'TRIAL';

    protected $fillable = [
        'entitlement_id',
        'tenant_id',
        'feature_code',
        'is_enabled',
        'source',
        'effective_from',
        'effective_to',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled'     => 'boolean',
            'effective_from' => 'datetime',
            'effective_to'   => 'datetime',
            'row_version'    => 'integer',
        ];
    }
}
