<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CompanyNumberingSetting extends Model
{
    use HasUuids, TenantScoped;

    protected $table = 'fin_acc_company_numbering_settings';

    protected $primaryKey = 'setting_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_series_enabled',
        'branch_series_from_fiscal_year',
        'updated_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'branch_series_enabled' => 'boolean',
            'row_version'           => 'integer',
            'created_at'            => 'datetime',
            'updated_at'            => 'datetime',
        ];
    }
}
