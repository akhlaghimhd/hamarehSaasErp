<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Base\Traits\TenantScoped;

class TenantSodRule extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'tenant_sod_rules';
    protected $primaryKey = 'sod_rule_id';

    public $incrementing = false;
    protected $keyType = 'string';

    public const SEVERITY_LOW = 1;
    public const SEVERITY_MEDIUM = 2;
    public const SEVERITY_HIGH = 3;
    public const SEVERITY_CRITICAL = 4;

    public const ENFORCEMENT_BLOCK = 'BLOCK';
    public const ENFORCEMENT_WARN = 'WARN';

    protected $fillable = [
        'sod_rule_id',
        'tenant_id',
        'role_a_id',
        'role_b_id',
        'code',
        'name',
        'description',
        'severity',
        'enforcement',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'severity'  => 'integer',
            'is_active' => 'boolean',
            'row_version' => 'integer',
        ];
    }

    public function roleA(): BelongsTo
    {
        return $this->belongsTo(TenantRole::class, 'role_a_id', 'tenant_role_id');
    }

    public function roleB(): BelongsTo
    {
        return $this->belongsTo(TenantRole::class, 'role_b_id', 'tenant_role_id');
    }
}
