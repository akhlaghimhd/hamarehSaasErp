<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    use HasUuids, TenantScoped;

    public $timestamps = false;

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_MATCHED = 'MATCHED';

    protected $table = 'fin_acc_bank_statement_lines';

    protected $primaryKey = 'bank_statement_line_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'bank_statement_id',
        'tenant_id',
        'line_date',
        'description',
        'debit_amount',
        'credit_amount',
        'status',
        'matched_treasury_document_id',
        'matched_journal_entry_id',
        'sort_order',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'line_date'     => 'date',
            'debit_amount'  => 'decimal:4',
            'credit_amount' => 'decimal:4',
            'created_at'    => 'datetime',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id', 'bank_statement_id');
    }
}
