<?php

namespace App\Modules\HrManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'payroll_records';
    protected $primaryKey = 'payroll_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'fiscal_period_id',
        'base_salary',
        'allowances_total',
        'deductions_total',
        'tax_withheld',
        'insurance_premium',
        'is_disbursed',
        'disbursed_at',
        'journal_entry_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'base_salary' => 'decimal:4',
        'allowances_total' => 'decimal:4',
        'deductions_total' => 'decimal:4',
        'tax_withheld' => 'decimal:4',
        'insurance_premium' => 'decimal:4',
        'net_payable' => 'decimal:4',
        'is_disbursed' => 'boolean',
        'disbursed_at' => 'datetime',
        'row_version' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
