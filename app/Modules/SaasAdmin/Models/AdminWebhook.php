<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AdminWebhook extends Model
{
    use HasUuids;

    protected $table = 'admin_webhooks';
    protected $primaryKey = 'webhook_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'name', 'target_url', 'secret_token', 'event_types', 'is_active',
        'created_at', 'updated_at', 'row_version',
    ];

    protected $hidden = ['secret_token'];

    protected function casts(): array
    {
        return [
            'event_types' => 'array',
            'is_active' => 'boolean',
            'row_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
