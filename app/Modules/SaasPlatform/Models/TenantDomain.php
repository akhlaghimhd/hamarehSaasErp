<?php

namespace App\Modules\SaasPlatform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tenant_domains';
    protected $primaryKey = 'tenant_domain_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'domain_name',
        'domain_type',
        'is_primary',
        'verification_token',
        'verified_at',
        'ssl_status',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'domain_type' => 'integer',
            'is_primary' => 'boolean',
            'ssl_status' => 'integer',
            'status' => 'integer',
            'verified_at' => 'datetime',
            'row_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }
}
