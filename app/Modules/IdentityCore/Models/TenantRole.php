<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

class TenantRole extends Model
{
    use HasUuids, HasFactory, TenantScoped, SoftDeletes;

    protected $table = 'tenant_roles';
    protected $primaryKey = 'tenant_role_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
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

    public function permissions()
    {
        return $this->belongsToMany(
            TenantPermission::class,
            'tenant_role_permissions',
            'tenant_role_id',
            'tenant_permission_id'
        );
    }

    public function userRoles()
    {
        return $this->hasMany(TenantUserRole::class, 'tenant_role_id', 'tenant_role_id');
    }

    protected static function newFactory()
    {
        return \Database\Factories\TenantRoleFactory::new();
    }

    public static function find($id)
    {
        return static::where('tenant_role_id', $id)->first();
    }
}
