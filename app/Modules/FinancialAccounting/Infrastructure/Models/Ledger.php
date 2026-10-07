<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ledger extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'fin_acc_ledgers';

    protected $primaryKey = 'ledger_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'code',
        'name',
        'is_leading',
        'base_currency_id',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_leading'  => 'boolean',
            'status'      => 'integer',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'ledger_id', 'ledger_id');
    }
}
