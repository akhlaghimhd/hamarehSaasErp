<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Base\Traits\TenantScoped;

class TenantMembershipHistory extends Model
{
    use SoftDeletes, HasUuids, TenantScoped;

    protected $table = 'tenant_membership_histories';
    protected $primaryKey = 'history_id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'tenant_id',
        'tenant_user_id',
        'previous_status',
        'new_status',
        'reason_code',
        'description',
        'effective_date',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'previous_status' => 'integer',
        'new_status' => 'integer',
        'effective_date' => 'datetime',
        'row_version' => 'integer',
    ];

    /**
     * ارتباط با موجودیت اصلی عضویت (TenantUser)
     */
    public function tenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'tenant_user_id', 'tenant_user_id');
    }
}
