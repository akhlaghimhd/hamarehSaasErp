<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SuggestedJournal extends Model
{
    use HasUuids, TenantScoped;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_ACCEPTED = 'ACCEPTED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $table = 'fin_acc_suggested_journals';

    protected $primaryKey = 'suggested_journal_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'ledger_id',
        'period_id',
        'source_event_type',
        'source_document_id',
        'status',
        'description',
        'journal_entry_id',
        'decided_by',
        'decided_at',
        'decision_note',
        'created_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'decided_at'  => 'datetime',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SuggestedJournalLine::class, 'suggested_journal_id', 'suggested_journal_id')
            ->orderBy('sort_order');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
