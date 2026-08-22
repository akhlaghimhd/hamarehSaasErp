<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesDeliveryOrder extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'sales_delivery_orders';
    protected $primaryKey = 'delivery_id';

    protected $fillable = [
        'tenant_id',
        'sales_order_id', // UUID منطقی - اشاره به سفارش فروش (اختیاری)
        'customer_id',    // UUID منطقی - مشتری
        'warehouse_id',   // UUID منطقی - انباری که کالا از آن خارج می‌شود
        'delivery_number',
        'delivery_date',
        'total_amount',
        'status',         // 1: Draft, 2: Shipped, 3: Delivered, 4: Cancelled
        'row_version'
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'total_amount' => 'decimal:4',
    ];

    public function items()
    {
        return $this->hasMany(SalesDeliveryOrderItem::class, 'delivery_id', 'delivery_id');
    }
}