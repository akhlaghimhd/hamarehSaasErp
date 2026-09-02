<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

class FiscalPeriod extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'fin_fiscal_periods';
    protected $primaryKey = 'period_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'name',
        'start_date',
        'end_date',
        'is_closed',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_closed' => 'boolean',
        'row_version' => 'integer',
    ];
}
