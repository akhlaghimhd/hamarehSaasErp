<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesDeliveryOrderItem extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'sales_delivery_order_items';
    protected $primaryKey = 'delivery_item_id';
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'delivery_id',
        'item_id', // UUID منطقی - اشاره به کالا در MasterData
        'delivered_quantity',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'delivered_quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'total_price' => 'decimal:4',
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(SalesDeliveryOrder::class, 'delivery_id', 'delivery_id');
    }
}