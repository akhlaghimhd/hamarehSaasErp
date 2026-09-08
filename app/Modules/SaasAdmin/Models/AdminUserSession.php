<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AdminUserSession extends Model
{
    use HasUuids;

    protected $table = 'admin_user_sessions';
    protected $primaryKey = 'session_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'admin_user_id', 'token_hash', 'ip_address', 'user_agent',
        'is_active', 'created_at', 'expires_at', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }
}
