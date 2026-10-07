<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SuggestedJournalLine extends Model
{
    use HasUuids, TenantScoped;

    public $timestamps = false;

    protected $table = 'fin_acc_suggested_journal_lines';

    protected $primaryKey = 'suggested_line_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'suggested_journal_id',
        'tenant_id',
        'account_id',
        'debit_amount',
        'credit_amount',
        'line_role',
        'suggestion_reason',
        'sort_order',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'debit_amount'  => 'decimal:4',
            'credit_amount' => 'decimal:4',
            'created_at'    => 'datetime',
        ];
    }
}
