<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalItem extends Model
{
    use HasUuids, TenantScoped;

    public $timestamps = false;

    protected $table = 'fin_acc_journal_items';

    protected $primaryKey = 'journal_item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'journal_entry_id',
        'tenant_id',
        'account_id',
        'cost_center_id',
        'business_unit_id',
        'debit_amount',
        'credit_amount',
        'currency_id',
        'exchange_rate',
        'source_currency_amount',
        'description',
        'sort_order',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'debit_amount'           => 'decimal:4',
            'credit_amount'          => 'decimal:4',
            'exchange_rate'          => 'decimal:8',
            'source_currency_amount' => 'decimal:4',
            'sort_order'             => 'integer',
            'created_at'             => 'datetime',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id', 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }
}
