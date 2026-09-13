<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IdentityLoginOtp extends Model
{
    use HasUuids;

    protected $table = 'identity_login_otps';

    protected $primaryKey = 'otp_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'mobile',
        'code_hash',
        'expires_at',
        'last_sent_at',
        'consumed_at',
        'attempt_count',
        'request_ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'    => 'datetime',
            'last_sent_at'  => 'datetime',
            'consumed_at'   => 'datetime',
            'attempt_count' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
