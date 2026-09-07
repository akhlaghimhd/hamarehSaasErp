<?php

namespace App\Modules\HrManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * attendance_records — aligned to migration columns.
 * status: 1 Present, 2 Absent, 3 Leave, 4 Mission
 */
class AttendanceRecord extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'attendance_records';
    protected $primaryKey = 'attendance_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'attendance_date',
        'clock_in',
        'clock_out',
        'overtime_hours',
        'delay_hours',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'clock_in'        => 'datetime',
        'clock_out'       => 'datetime',
        'overtime_hours'  => 'decimal:4',
        'delay_hours'     => 'decimal:4',
        'status'          => 'integer',
        'row_version'     => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
