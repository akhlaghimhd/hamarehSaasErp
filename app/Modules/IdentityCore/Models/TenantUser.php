<?php

namespace App\Modules\IdentityCore\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TenantUser extends Model
{
    use HasUuids, HasFactory, TenantScoped;

    protected $table = 'tenant_users';
    protected $primaryKey = 'tenant_user_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'username',
        'tenant_user_email',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Modules\SaasAdmin\Models\Tenant::class, 'tenant_id', 'tenant_id');
    }

    // ✅ این متد را اضافه کنید تا لاراول فکتوری را در مسیر درست پیدا کند
    protected static function newFactory()
    {
        return \Database\Factories\TenantUserFactory::new();
    }
}