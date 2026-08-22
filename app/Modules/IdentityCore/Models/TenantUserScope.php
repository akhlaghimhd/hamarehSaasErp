<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Base\Traits\TenantScoped;

class TenantUserScope extends Model
{
    use SoftDeletes, HasUuids, TenantScoped;

    protected $table = 'tenant_user_scopes';
    protected $primaryKey = 'assignment_id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'tenant_id',
        'tenant_user_id',
        'scope_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'row_version' => 'integer',
    ];

    public function tenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'tenant_user_id', 'tenant_user_id');
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(TenantScope::class, 'scope_id', 'scope_id');
    }
}
