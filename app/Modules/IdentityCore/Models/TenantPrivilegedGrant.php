<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantPrivilegedGrant extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_REVOKED = 'REVOKED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_DENIED = 'DENIED';

    protected $table = 'tenant_privileged_grants';

    protected $primaryKey = 'grant_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'grant_id',
        'tenant_id',
        'user_id',
        'tenant_role_id',
        'reason',
        'status',
        'duration_minutes',
        'starts_at',
        'ends_at',
        'requested_by',
        'approved_by',
        'approved_at',
        'revoked_by',
        'revoked_at',
        'revoke_reason',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'starts_at'        => 'datetime',
            'ends_at'          => 'datetime',
            'approved_at'      => 'datetime',
            'revoked_at'       => 'datetime',
            'row_version'      => 'integer',
            'created_at'       => 'datetime',
            'updated_at'       => 'datetime',
            'deleted_at'       => 'datetime',
        ];
    }
}
