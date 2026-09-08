<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'audit_logs';

    protected $primaryKey = 'audit_log_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false; // only created_at + log_date

    protected $fillable = [
        'tenant_id',
        'user_id',
        'admin_user_id',
        'session_id',
        'request_id',
        'entity_name',
        'entity_id',
        'action_type',
        'severity',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'details',
        'log_date',
        'created_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'severity'   => 'integer',
            'old_values' => 'array',
            'new_values' => 'array',
            'details'    => 'array',
            'log_date'   => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
