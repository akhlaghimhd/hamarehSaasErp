<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IcJournalPair extends Model
{
    use SoftDeletes, TenantScoped;

    public const STATUS_DRAFT_PAIR = 'DRAFT_PAIR';
    public const STATUS_PARTIAL_POSTED = 'PARTIAL_POSTED';
    public const STATUS_POSTED = 'POSTED';

    protected $table = 'fin_acc_ic_journal_pairs';

    protected $primaryKey = 'ic_journal_pair_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'ic_journal_pair_id',
        'tenant_id',
        'from_company_id',
        'to_company_id',
        'from_journal_entry_id',
        'to_journal_entry_id',
        'period_id',
        'amount',
        'description',
        'status',
        'ic_partner_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:4',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }
}
