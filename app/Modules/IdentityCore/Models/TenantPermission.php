<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;

class TenantPermission extends Model
{
    use HasUuids, TenantScoped;

    protected $table = 'tenant_permissions';
    protected $primaryKey = 'tenant_permission_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'module_name',
        'action_type',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    public function roles()
    {
        return $this->belongsToMany(
            TenantRole::class,
            'tenant_role_permissions',
            'tenant_permission_id',
            'tenant_role_id'
        );
    }
}