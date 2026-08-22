<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class WorkCenter extends Model
{
    use TenantScoped, HasUuids;

    protected $table = 'mfg_work_centers';
    protected $primaryKey = 'work_center_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'capacity_hours_per_day',
        'efficiency_percentage',
        'cost_per_hour',
        'status',
        'created_by'
    ];
}