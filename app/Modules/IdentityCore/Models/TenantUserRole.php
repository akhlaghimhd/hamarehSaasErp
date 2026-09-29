<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;

class TenantUserRole extends Model
{
    use HasUuids, TenantScoped;

    protected $table = 'tenant_user_roles';
    protected $primaryKey = 'tenant_user_role_id';

    protected $fillable = [
        'tenant_user_role_id',
        'tenant_id',
        'user_id',
        'tenant_role_id',
        'valid_from',
        'valid_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_to'   => 'datetime',
        ];
    }

    public function role()
    {
        return $this->belongsTo(TenantRole::class, 'tenant_role_id', 'tenant_role_id');
    }

    public function statusHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TenantMembershipHistory::class, 'tenant_user_id', 'tenant_user_id');
    }

    public function scopes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TenantUserScope::class, 'tenant_user_id', 'tenant_user_id');
    }
}
