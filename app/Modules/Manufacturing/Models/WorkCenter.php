<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

class WorkCenter extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'mfg_work_centers';
    protected $primaryKey = 'work_center_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'capacity_hours_per_day',
        'efficiency_percentage',
        'cost_per_hour',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'capacity_hours_per_day' => 'decimal:2',
        'efficiency_percentage' => 'decimal:2',
        'cost_per_hour' => 'decimal:4',
        'status' => 'integer',
        'row_version' => 'integer',
    ];
}
