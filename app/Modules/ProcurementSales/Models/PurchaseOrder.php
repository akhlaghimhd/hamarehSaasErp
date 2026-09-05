<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'purchase_orders';
    protected $primaryKey = 'purchase_order_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'order_number',
        'supplier_id',
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
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id', 'purchase_order_id');
    }
}
