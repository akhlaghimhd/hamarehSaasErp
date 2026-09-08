<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AdminLoginAttempt extends Model
{
    use HasUuids;

    protected $table = 'admin_login_attempts';
    protected $primaryKey = 'attempt_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'username', 'ip_address', 'user_agent', 'is_successful',
        'failure_reason', 'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_successful' => 'boolean',
            'attempted_at' => 'datetime',
        ];
    }
}
