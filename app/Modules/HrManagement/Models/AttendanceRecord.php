<?php

namespace App\Modules\HrManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'attendance_records';
    protected $primaryKey = 'attendance_id';
    
    protected $fillable = [
        'tenant_id',
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'status', // 1: Present, 2: Absent, 3: On Leave, 4: Half-day
        'work_hours',
        'overtime_hours',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'status' => 'integer',
        'work_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
    ];

    /**
     * ارتباط با موجودیت کارمند درون همین ماژول
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}