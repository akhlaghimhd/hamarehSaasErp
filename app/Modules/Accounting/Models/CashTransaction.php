<?php

namespace App\Modules\Accounting\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashTransaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'fin_cash_transactions';
    protected $primaryKey = 'cash_transaction_id';
    public $incrementing = false;
    protected $keyType = 'string';

    public const TYPE_RECEIPT = 1;
    public const TYPE_PAYMENT = 2;

    public const STATUS_PENDING = 1;
    public const STATUS_CLEARED = 2;
    public const STATUS_REJECTED = 3;

    protected $fillable = [
        'tenant_id',
        'payment_schedule_id',
        'bank_account_id',
        'transaction_type',
        'amount',
        'payment_reference',
        'transaction_date',
        'status',
        'accounting_voucher_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'amount'            => 'decimal:4',
        'transaction_date'  => 'datetime',
        'transaction_type'  => 'integer',
        'status'            => 'integer',
        'row_version'       => 'integer',
    ];

    public function schedule()
    {
        return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id', 'payment_schedule_id');
    }
}
