<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ComplianceAlert extends Model
{
    use HasUuids, TenantScoped;

    public $timestamps = false;

    public const SEV_INFO = 'INFO';

    public const SEV_WARN = 'WARN';

    public const SEV_BLOCK = 'BLOCK';

    protected $table = 'fin_acc_compliance_alerts';

    protected $primaryKey = 'compliance_alert_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'alert_code',
        'severity',
        'title',
        'message',
        'related_type',
        'related_id',
        'is_resolved',
        'resolved_at',
        'resolved_by',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
            'created_at'  => 'datetime',
        ];
    }
}
