<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;

class FiscalPeriod extends Model
{
    use HasUuids, TenantScoped;

    protected $table = 'fin_fiscal_periods';
    protected $primaryKey = 'period_id';

    protected $fillable = [
        'tenant_id',
        'name',
        'start_date',
        'end_date',
        'is_closed',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_closed' => 'boolean',
    ];
}