<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;

class FinancialVoucherItem extends Model
{
    use HasUuids, TenantScoped;

    protected $table = 'fin_voucher_items';
    protected $primaryKey = 'item_id';

    protected $fillable = [
        'tenant_id',
        'voucher_id',
        'account_id',
        'cost_center_id',
        'business_partner_id',
        'description',
        'debit',
        'credit',
    ];

    protected $casts = [
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
    ];

    // ارتباط با سربرگ سند (درون همان ماژول)
    public function voucher()
    {
        return $this->belongsTo(FinancialVoucher::class, 'voucher_id', 'voucher_id');
    }
}