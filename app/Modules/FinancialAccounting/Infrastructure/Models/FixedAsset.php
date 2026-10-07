<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_DISPOSED = 'DISPOSED';
    public const STATUS_FULLY_DEPRECIATED = 'FULLY_DEPRECIATED';

    protected $table = 'fin_acc_fixed_assets';

    protected $primaryKey = 'fixed_asset_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'asset_code',
        'name',
        'asset_account_id',
        'accum_depr_account_id',
        'depr_expense_account_id',
        'cost_center_id',
        'acquisition_date',
        'acquisition_cost',
        'salvage_value',
        'useful_life_months',
        'depreciation_method',
        'book_value',
        'accumulated_depreciation',
        'status',
        'last_depreciated_through',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date'          => 'date',
            'last_depreciated_through'  => 'date',
            'acquisition_cost'          => 'decimal:4',
            'salvage_value'             => 'decimal:4',
            'book_value'                => 'decimal:4',
            'accumulated_depreciation'  => 'decimal:4',
            'useful_life_months'        => 'integer',
            'row_version'               => 'integer',
            'created_at'                => 'datetime',
            'updated_at'                => 'datetime',
            'deleted_at'                => 'datetime',
        ];
    }
}
