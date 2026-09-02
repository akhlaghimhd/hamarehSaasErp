<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

class FinancialVoucher extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'fin_vouchers';
    protected $primaryKey = 'voucher_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'voucher_date',
        'description',
        'total_amount',
        'reference_number',
        'currency_id',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'total_amount' => 'decimal:4',
        'status' => 'integer',
        'row_version' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(FinancialVoucherItem::class, 'voucher_id', 'voucher_id');
    }
}
