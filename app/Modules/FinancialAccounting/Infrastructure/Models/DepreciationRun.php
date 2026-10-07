<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepreciationRun extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_POSTED_AS_JOURNAL = 'POSTED_AS_JOURNAL';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'fin_acc_depreciation_runs';

    protected $primaryKey = 'depreciation_run_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'period_id',
        'ledger_id',
        'run_date',
        'status',
        'journal_entry_id',
        'total_amount',
        'asset_count',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'run_date'      => 'date',
            'total_amount'  => 'decimal:4',
            'asset_count'   => 'integer',
            'row_version'   => 'integer',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
            'deleted_at'    => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DepreciationRunLine::class, 'depreciation_run_id', 'depreciation_run_id');
    }
}
