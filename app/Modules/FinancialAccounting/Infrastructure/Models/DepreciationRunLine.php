<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class DepreciationRunLine extends Model
{
    use TenantScoped;

    public $timestamps = false;

    protected $table = 'fin_acc_depreciation_run_lines';

    protected $primaryKey = 'depreciation_line_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'depreciation_line_id',
        'tenant_id',
        'depreciation_run_id',
        'fixed_asset_id',
        'amount',
        'book_value_after',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:4',
            'book_value_after' => 'decimal:4',
            'created_at'       => 'datetime',
        ];
    }
}
