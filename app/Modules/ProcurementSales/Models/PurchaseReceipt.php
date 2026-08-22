<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReceipt extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'purchase_receipts';
    protected $primaryKey = 'receipt_id';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id', // UUID منطقی - اشاره به سفارش خرید (می‌تواند Null باشد اگر رسید بدون سفارش است)
        'supplier_id',       // UUID منطقی - اشاره به MasterData
        'warehouse_id',      // UUID منطقی - اشاره به MasterData (انباری که کالا وارد آن می‌شود)
        'receipt_number',
        'receipt_date',
        'total_amount',
        'status',            // 1: Draft, 2: Approved, 3: Cancelled
        'row_version'
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'total_amount' => 'decimal:4',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseReceiptItem::class, 'receipt_id', 'receipt_id');
    }
}