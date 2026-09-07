<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesDeliveryOrderItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'sales_delivery_order_items';
    protected $primaryKey = 'sales_delivery_order_item_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'delivery_order_id',
        'sales_order_item_id',
        'item_id',
        'ordered_quantity',
        'delivered_quantity',
        'unit_price',
        'total_price',
        'uom_code',
        'line_number',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'ordered_quantity'   => 'decimal:4',
        'delivered_quantity' => 'decimal:4',
        'unit_price'         => 'decimal:4',
        'total_price'        => 'decimal:4',
        'line_number'        => 'integer',
        'row_version'        => 'integer',
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(SalesDeliveryOrder::class, 'delivery_order_id', 'delivery_order_id');
    }
}
