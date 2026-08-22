<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Base\Traits\TenantScoped;

class TenantRole extends Model
{
    use HasUuids, HasFactory, TenantScoped; // ✅ HasFactory اضافه شد

    protected $table = 'tenant_roles';
    protected $primaryKey = 'tenant_role_id'; 
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'code', 'name', 'description', 'status', 'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return ['status' => 'integer'];
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

    // ✅ این متد برای معرفی دقیق مسیر فکتوری به لاراول الزامی است
    protected static function newFactory()
    {
        return \Database\Factories\TenantRoleFactory::new();
    }

    public static function find($id)
    {
        return static::where('tenant_role_id', $id)->first();
    }
}