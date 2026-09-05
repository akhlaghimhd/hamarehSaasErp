<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'sales_orders';
    protected $primaryKey = 'sales_order_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'order_number',
        'customer_id',
        'quotation_id',
        'order_date',
        'delivery_date',
        'subtotal_amount',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'status',
        'currency_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'order_date'      => 'date',
        'delivery_date'   => 'date',
        'subtotal_amount' => 'decimal:4',
        'tax_amount'      => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'total_amount'    => 'decimal:4',
        'status'          => 'integer',
        'row_version'     => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id', 'sales_order_id');
    }
}
