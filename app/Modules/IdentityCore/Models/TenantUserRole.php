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
        'tenant_id',
        'user_id', // ارتباط منطقی (UUID) با جدول کاربران
        'tenant_role_id',
        'created_by'
    ];

    public function role()
    {
        return $this->belongsTo(TenantRole::class, 'tenant_role_id', 'tenant_role_id');
    }
    
    /**
     * تاریخچه وضعیت‌های عضویت این کاربر
     */
    public function statusHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TenantMembershipHistory::class, 'tenant_user_id', 'tenant_user_id');
    }
    /**
     * محدوده‌های دسترسی تخصیص‌یافته به کاربر
     */
    public function scopes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TenantUserScope::class, 'tenant_user_id', 'tenant_user_id');
    }
}
