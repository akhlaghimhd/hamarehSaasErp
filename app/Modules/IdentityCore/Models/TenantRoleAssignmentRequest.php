<?php

namespace App\Modules\IdentityCore\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantRoleAssignmentRequest extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const ACTION_GRANT = 'GRANT';
    public const ACTION_REVOKE = 'REVOKE';

    protected $table = 'tenant_role_assignment_requests';
    protected $primaryKey = 'request_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'request_id',
        'tenant_id',
        'user_id',
        'tenant_role_id',
        'request_action',
        'status',
        'valid_from',
        'valid_to',
        'reason',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'valid_from'  => 'datetime',
            'valid_to'    => 'datetime',
            'row_version' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function role()
    {
        return $this->belongsTo(TenantRole::class, 'tenant_role_id', 'tenant_role_id');
    }
}
