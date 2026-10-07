<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use HasUuids, TenantScoped;

    protected $table = 'fin_acc_document_sequences';

    protected $primaryKey = 'sequence_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'sequence_key',
        'fiscal_year_key',
        'prefix',
        'next_number',
        'pad_length',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
            'pad_length'  => 'integer',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
        ];
    }
}
