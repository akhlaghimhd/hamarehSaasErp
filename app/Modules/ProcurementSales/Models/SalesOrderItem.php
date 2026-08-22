<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'sales_order_items';
    protected $primaryKey = 'sales_order_item_id';
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'sales_order_id',
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

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id', 'sales_order_id');
    }
}