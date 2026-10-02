<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantAccessCertItem extends Model
{
    use SoftDeletes;

    public const DECISION_PENDING = 'PENDING';
    public const DECISION_APPROVED = 'APPROVED';
    public const DECISION_REVOKE_REQUESTED = 'REVOKE_REQUESTED';
    public const DECISION_DEFERRED = 'DEFERRED';
    /** Gap was fixed (roles changed) and confirmed on re-evaluate — keep for audit/report. */
    public const DECISION_RESOLVED = 'RESOLVED';

    protected $table = 'tenant_access_cert_items';

    protected $primaryKey = 'item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'item_id',
        'tenant_id',
        'campaign_id',
        'tenant_user_id',
        'user_id',
        'role_ids_snapshot',
        'sod_has_block',
        'sod_has_warn',
        'sod_conflicts',
        'decision',
        'reviewer_user_id',
        'decided_at',
        'decision_note',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'role_ids_snapshot' => 'array',
            'sod_conflicts'     => 'array',
            'sod_has_block'     => 'boolean',
            'sod_has_warn'      => 'boolean',
            'decided_at'        => 'datetime',
            'row_version'       => 'integer',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
            'deleted_at'        => 'datetime',
        ];
    }
}
