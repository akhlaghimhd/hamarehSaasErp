<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AdminApiKey extends Model
{
    use HasUuids;

    protected $table = 'admin_api_keys';
    protected $primaryKey = 'api_key_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'admin_user_id', 'name', 'key_prefix', 'key_hash', 'is_active',
        'created_at', 'expires_at', 'last_used_at', 'row_version',
    ];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'row_version' => 'integer',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
