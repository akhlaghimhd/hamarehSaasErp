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

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'sales_order_id',
        'customer_id',
        'warehouse_id',
        'delivery_number',
        'delivery_date',
        'total_amount',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'total_amount' => 'decimal:4',
        'status' => 'integer',
        'row_version' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(SalesDeliveryOrderItem::class, 'delivery_id', 'delivery_id');
    }
}
