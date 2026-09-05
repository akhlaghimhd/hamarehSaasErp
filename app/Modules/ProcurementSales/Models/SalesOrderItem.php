<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrderItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'sales_order_items';
    protected $primaryKey = 'sales_order_item_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'sales_order_id',
        'item_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'total_price',
        'uom_code',
        'line_number',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'quantity'        => 'decimal:4',
        'unit_price'      => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_amount'      => 'decimal:4',
        'total_price'     => 'decimal:4',
        'line_number'     => 'integer',
        'row_version'     => 'integer',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id', 'sales_order_id');
    }
}
