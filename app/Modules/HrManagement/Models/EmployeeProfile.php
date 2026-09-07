<?php

namespace App\Modules\HrManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'employee_profiles';
    protected $primaryKey = 'profile_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'national_code',
        'father_name',
        'gender',
        'marital_status',
        'birth_date',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'gender'         => 'integer',
        'marital_status' => 'integer',
        'birth_date'     => 'date',
        'row_version'    => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
