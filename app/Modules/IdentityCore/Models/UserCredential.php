<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UserCredential extends Model
{
    use HasUuids; // بدون TenantScoped (متصل به کاربر سراسری)

    protected $table = 'user_credentials';
    protected $primaryKey = 'credential_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'password_hash',
        'must_set_password',
        'authentication_type', // 1: Password, 2: OTP, 3: OAuth
        'is_verified',
        'two_factor_enabled',
        'failed_login_count',
        'locked_until',
        'last_password_change_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_verified'             => 'boolean',
            'must_set_password'       => 'boolean',
            'two_factor_enabled'     => 'boolean',
            'failed_login_count'      => 'integer',
            'authentication_type'     => 'integer',
            'locked_until'            => 'datetime',
            'last_password_change_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
