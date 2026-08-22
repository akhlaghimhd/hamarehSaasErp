<?php

namespace App\Modules\HrManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

class Employee extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'employees';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'business_partner_id', // ارجاع منطقی به MasterData
        'user_id',             // ارجاع منطقی به IdentityCore
        'employee_code',
        'employment_type',     // 1: Full Time, 2: Part Time, 3: Contract
        'hire_date',
        'termination_date',
        'job_title',
        'department_id',       // ارجاع منطقی به CostCenter در MasterData
        'branch_id',
        'status',              // 1: Active, 2: Suspended, 3: Terminated
    ];

    protected $casts = [
        'hire_date'        => 'date',
        'termination_date' => 'date',
        'employment_type'  => 'integer',
        'status'           => 'integer',
    ];
}