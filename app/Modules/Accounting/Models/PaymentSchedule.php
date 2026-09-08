<?php

namespace App\Modules\Accounting\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentSchedule extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'fin_payment_schedules';
    protected $primaryKey = 'payment_schedule_id';
    public $incrementing = false;
    protected $keyType = 'string';

    public const STATUS_PENDING = 1;
    public const STATUS_PARTIALLY_PAID = 2;
    public const STATUS_SETTLED = 3;
    public const STATUS_OVERDUE = 4;

    public const SOURCE_SAL_INVOICE = 'SAL_INVOICE';
    public const SOURCE_PUR_INVOICE = 'PUR_INVOICE';

    protected $fillable = [
        'tenant_id',
        'source_document_type',
        'source_document_id',
        'currency_id',
        'due_date',
        'expected_amount',
        'paid_amount',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'due_date'         => 'date',
        'expected_amount'  => 'decimal:4',
        'paid_amount'      => 'decimal:4',
        'status'           => 'integer',
        'row_version'      => 'integer',
    ];

    public function cashTransactions()
    {
        return $this->hasMany(CashTransaction::class, 'payment_schedule_id', 'payment_schedule_id');
    }
}
