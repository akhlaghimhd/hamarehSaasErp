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
    protected $primaryKey = 'employee_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'business_partner_id',
        'user_id',
        'employee_code',
        'employment_type',
        'hire_date',
        'termination_date',
        'job_title',
        'department_id',
        'branch_id',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'hire_date'        => 'date',
        'termination_date' => 'date',
        'employment_type'  => 'integer',
        'status'           => 'integer',
        'row_version'      => 'integer',
    ];
}
