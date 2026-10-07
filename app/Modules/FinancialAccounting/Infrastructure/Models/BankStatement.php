<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankStatement extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_RECONCILED = 'RECONCILED';

    protected $table = 'fin_acc_bank_statements';

    protected $primaryKey = 'bank_statement_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'cash_account_id',
        'statement_date',
        'reference',
        'opening_balance',
        'closing_balance',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'statement_date'  => 'date',
            'opening_balance' => 'decimal:4',
            'closing_balance' => 'decimal:4',
            'row_version'     => 'integer',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
            'deleted_at'      => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'bank_statement_id', 'bank_statement_id')
            ->orderBy('sort_order');
    }
}
