<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SmartActionLog extends Model
{
    use TenantScoped;

    public $timestamps = false;

    protected $table = 'fin_acc_smart_action_logs';

    protected $primaryKey = 'smart_action_log_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'smart_action_log_id',
        'tenant_id',
        'action_type',
        'feature_code',
        'actor_id',
        'decision',
        'payload',
        'related_entity_type',
        'related_entity_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'created_at' => 'datetime',
        ];
    }
}
