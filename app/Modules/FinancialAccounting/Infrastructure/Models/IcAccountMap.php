<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IcAccountMap extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'fin_acc_ic_account_maps';

    protected $primaryKey = 'ic_account_map_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'ic_account_map_id',
        'tenant_id',
        'from_company_id',
        'to_company_id',
        'due_from_account_id',
        'due_to_account_id',
        'ic_partner_id',
        'is_active',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }
}
