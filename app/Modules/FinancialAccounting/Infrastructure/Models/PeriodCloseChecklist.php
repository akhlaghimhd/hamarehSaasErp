<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PeriodCloseChecklist extends Model
{
    use HasUuids, TenantScoped;

    public $timestamps = false;

    protected $table = 'fin_acc_period_close_checklists';

    protected $primaryKey = 'checklist_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'period_id',
        'items_json',
        'has_blocking',
        'evaluated_at',
        'evaluated_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'items_json'   => 'array',
            'has_blocking' => 'boolean',
            'evaluated_at' => 'datetime',
            'created_at'   => 'datetime',
        ];
    }
}
