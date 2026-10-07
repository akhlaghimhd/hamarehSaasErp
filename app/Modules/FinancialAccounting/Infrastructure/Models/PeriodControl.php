<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PeriodControl extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_SOFT_CLOSED = 'SOFT_CLOSED';
    public const STATUS_HARD_CLOSED = 'HARD_CLOSED';

    protected $table = 'fin_acc_period_controls';

    protected $primaryKey = 'period_control_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'period_id',
        'control_status',
        'soft_closed_at',
        'soft_closed_by',
        'hard_closed_at',
        'hard_closed_by',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'soft_closed_at' => 'datetime',
            'hard_closed_at' => 'datetime',
            'row_version'    => 'integer',
            'created_at'     => 'datetime',
            'updated_at'     => 'datetime',
            'deleted_at'     => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->control_status === self::STATUS_OPEN;
    }

    public function isHardClosed(): bool
    {
        return $this->control_status === self::STATUS_HARD_CLOSED;
    }
}
