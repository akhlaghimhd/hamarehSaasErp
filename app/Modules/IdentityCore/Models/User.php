<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use App\Modules\IdentityCore\Models\TenantUser;

class User extends Authenticatable
{
    use HasUuids, HasFactory, HasApiTokens, SoftDeletes;

    protected $table = 'users';
    protected $primaryKey = 'user_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'mobile',
        'email',
        'first_name',
        'last_name',
        'user_kind',
        'status',
        'last_login_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'user_kind'     => 'integer',
            'status'        => 'integer',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * ارتباط یک به یک کاربر با اطلاعات امنیتی (رمز عبور و...)
     */
    public function credential()
    {
        return $this->hasOne(UserCredential::class, 'user_id', 'user_id');
    }

    /**
     * عضویت کاربر در مستأجران مختلف
     */
    public function tenantMemberships()
    {
        return $this->hasMany(TenantUser::class, 'user_id', 'user_id');
    }

    /**
     * ارتباط با پروفایل کاربر (1-to-1)
     */
    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id', 'user_id');
    }

    /**
     * اتصالات مدل به فکتوری
     */
    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }
}