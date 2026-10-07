<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreasuryDocument extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    public const TYPE_RECEIPT = 'RECEIPT';
    public const TYPE_PAYMENT = 'PAYMENT';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_POSTED = 'POSTED';
    public const STATUS_VOID = 'VOID';

    protected $table = 'fin_acc_treasury_documents';

    protected $primaryKey = 'treasury_document_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'period_id',
        'cash_account_id',
        'document_type',
        'document_number',
        'document_date',
        'status',
        'amount',
        'currency_id',
        'counterparty_name',
        'counterparty_open_item_id',
        'journal_entry_id',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'amount'        => 'decimal:4',
            'row_version'   => 'integer',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
            'deleted_at'    => 'datetime',
        ];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id', 'cash_account_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
