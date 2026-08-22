<?php

namespace App\Modules\HrManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'payroll_records';
    protected $primaryKey = 'payroll_id';
    
    // در این جدول SoftDeletes قرار ندادیم چون رکوردهای مالی معمولا یا قطعی هستند یا باطل می‌شوند.
    // اگر در دیتابیس شما SoftDeletes تعریف شده، می‌توانید آن را اضافه کنید.

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'fiscal_period_id', // ارجاع منطقی به دوره مالی در ماژول حسابداری
        'base_salary',
        'allowances_total',
        'deductions_total',
        'tax_withheld',
        'insurance_premium',
        // فیلد net_payable طبق سند در دیتابیس به صورت GENERATED ALWAYS است، پس fillable نیست
        'is_disbursed',
        'disbursed_at',
        'journal_entry_id', // ارجاع منطقی به سند حسابداری
    ];

    protected $casts = [
        'base_salary' => 'decimal:4',
        'allowances_total' => 'decimal:4',
        'deductions_total' => 'decimal:4',
        'tax_withheld' => 'decimal:4',
        'insurance_premium' => 'decimal:4',
        'net_payable' => 'decimal:4', // فیلد محاسبه شده در دیتابیس
        'is_disbursed' => 'boolean',
        'disbursed_at' => 'datetime',
    ];

    /**
     * ارتباط با موجودیت کارمند درون همین ماژول
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}