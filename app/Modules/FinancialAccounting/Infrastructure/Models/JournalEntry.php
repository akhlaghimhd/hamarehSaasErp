<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_POSTED = 'POSTED';
    public const STATUS_REVERSED = 'REVERSED';

    protected $table = 'fin_acc_journal_entries';

    protected $primaryKey = 'journal_entry_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'ledger_id',
        'company_id',
        'period_id',
        'entry_number',
        'document_date',
        'posting_date',
        'status',
        'source_document_type',
        'source_document_id',
        'reverses_entry_id',
        'reversed_by_entry_id',
        'description',
        'posted_by',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'posting_date'  => 'datetime',
            'row_version'   => 'integer',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
            'deleted_at'    => 'datetime',
        ];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'ledger_id', 'ledger_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'journal_entry_id', 'journal_entry_id')
            ->orderBy('sort_order');
    }

    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id', 'journal_entry_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }
}
