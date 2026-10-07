<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRateConfig extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'fin_acc_tax_rate_configs';

    protected $primaryKey = 'tax_rate_config_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'tax_code',
        'name',
        'rate_percent',
        'valid_from',
        'valid_to',
        'is_default',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:4',
            'valid_from'   => 'date',
            'valid_to'     => 'date',
            'is_default'   => 'boolean',
            'status'       => 'integer',
            'row_version'  => 'integer',
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
            'deleted_at'   => 'datetime',
        ];
    }
}
