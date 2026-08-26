<?php

namespace App\Modules\SaasPlatform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Database\Factories\TenantFactory;

class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tenants';

    protected $primaryKey = 'tenant_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_code',
        'tenant_name',
        'legal_name',
        'tenant_type',
        'slug',
        'primary_domain_enabled',
        'domain_status',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'tenant_type' => 'integer',
            'primary_domain_enabled' => 'boolean',
            'domain_status' => 'integer',
            'status' => 'integer',
        ];
    }

    protected static function newFactory()
    {
        return TenantFactory::new();
    }
}