<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'purchase_order_items';
    protected $primaryKey = 'purchase_order_item_id';
    public $timestamps = false; // معمولاً اقلام سفارش به تنهایی لاگ زمان نمی‌خواهند

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'item_id', // UUID منطقی - اشاره به MasterData (Item)
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'total_price' => 'decimal:4',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'purchase_order_id');
    }
}