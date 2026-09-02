<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

class FinancialVoucherItem extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'fin_voucher_items';
    protected $primaryKey = 'item_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'voucher_id',
        'account_id',
        'cost_center_id',
        'business_partner_id',
        'description',
        'debit',
        'credit',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
        'row_version' => 'integer',
    ];

    public function voucher()
    {
        return $this->belongsTo(FinancialVoucher::class, 'voucher_id', 'voucher_id');
    }
}
