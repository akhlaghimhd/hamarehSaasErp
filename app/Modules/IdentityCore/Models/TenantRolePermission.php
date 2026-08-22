<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;

class TenantRolePermission extends Model
{
    use HasUuids, TenantScoped;

    protected $table = 'tenant_role_permissions';
    protected $primaryKey = 'tenant_role_permission_id';

    protected $fillable = [
        'tenant_id',
        'tenant_role_id',
        'tenant_permission_id',
        'created_by'
    ];
}