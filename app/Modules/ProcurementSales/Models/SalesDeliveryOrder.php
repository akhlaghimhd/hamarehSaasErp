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
    protected $primaryKey = 'delivery_order_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'delivery_number',
        'id_sales_order_source',
        'customer_id',
        'warehouse_id',
        'shipping_date',
        'status',
        'shipping_address',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'shipping_date' => 'datetime',
        'status'        => 'integer',
        'row_version'   => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(SalesDeliveryOrderItem::class, 'delivery_order_id', 'delivery_order_id');
    }
}
