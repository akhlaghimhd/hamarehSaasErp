<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaxTransaction extends Model
{
    use HasUuids, TenantScoped;

    public $timestamps = false;

    public const DIR_OUTPUT = 'OUTPUT';

    public const DIR_INPUT = 'INPUT';

    protected $table = 'fin_acc_tax_transactions';

    protected $primaryKey = 'tax_transaction_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'source_document_type',
        'source_document_id',
        'tax_rate_config_id',
        'tax_code',
        'taxable_amount',
        'tax_rate',
        'tax_amount',
        'transaction_date',
        'direction',
        'journal_entry_id',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'taxable_amount'   => 'decimal:4',
            'tax_rate'         => 'decimal:4',
            'tax_amount'       => 'decimal:4',
            'transaction_date' => 'date',
            'created_at'       => 'datetime',
        ];
    }
}
