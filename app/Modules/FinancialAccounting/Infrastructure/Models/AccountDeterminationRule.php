<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountDeterminationRule extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'fin_acc_account_determination_rules';

    protected $primaryKey = 'rule_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'event_type',
        'line_role',
        'account_id',
        'company_id',
        'priority',
        'is_active',
        'description',
        'created_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'priority'    => 'integer',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }
}
