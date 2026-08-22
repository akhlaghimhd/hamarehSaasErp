<?php

namespace App\Modules\HrManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'employee_profiles';
    protected $primaryKey = 'profile_id';
    
    protected $fillable = [
        'tenant_id',
        'employee_id',
        'first_name',
        'last_name',
        'national_code',
        'birth_date',
        'gender', // 1: Male, 2: Female, 3: Other
        'marital_status', // 1: Single, 2: Married
        'address',
        'emergency_contact',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'gender' => 'integer',
        'marital_status' => 'integer',
    ];

    /**
     * ارتباط یک‌به‌یک با موجودیت کارمند درون همین ماژول
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}