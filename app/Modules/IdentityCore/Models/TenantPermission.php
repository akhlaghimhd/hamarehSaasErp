<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

class TenantPermission extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

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
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'status'      => 'integer',
            'row_version' => 'integer',
        ];
    }

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
