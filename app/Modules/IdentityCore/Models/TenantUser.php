<?php

namespace App\Modules\IdentityCore\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantUser extends Model
{
    use HasUuids, HasFactory, TenantScoped, SoftDeletes;

    protected $table = 'tenant_users';
    protected $primaryKey = 'tenant_user_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'employee_id',
        'is_owner',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'status'   => 'integer',
            'is_owner' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Modules\SaasPlatform\Models\Tenant::class, 'tenant_id', 'tenant_id');
    }

    protected static function newFactory()
    {
        return \Database\Factories\TenantUserFactory::new();
    }
}