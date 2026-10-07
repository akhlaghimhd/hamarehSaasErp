<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SmartActionLog extends Model
{
    use HasUuids, TenantScoped;

    public $timestamps = false;

    protected $table = 'fin_acc_smart_action_logs';

    protected $primaryKey = 'smart_action_log_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'action_type',
        'subject_type',
        'subject_id',
        'actor_id',
        'payload_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
