<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;

class FinancialVoucher extends Model
{
    use HasUuids, TenantScoped;

    protected $table = 'fin_vouchers';
    protected $primaryKey = 'voucher_id';

    protected $fillable = [
        'tenant_id',
        'voucher_date',
        'description',
        'total_amount',
        'reference_number',
        'currency_id',
        'status', // e.g., 1: Draft, 2: Posted, 3: Cancelled
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'total_amount' => 'decimal:4',
        'status' => 'integer',
    ];
}