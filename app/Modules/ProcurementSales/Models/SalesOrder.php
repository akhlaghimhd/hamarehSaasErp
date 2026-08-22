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

    protected $fillable = [
        'tenant_id',
        'customer_id', // UUID منطقی - اشاره به MasterData (BusinessPartner)
        'order_number',
        'order_date',
        'expected_delivery_date',
        'total_amount',
        'status', // 1: Draft, 2: Confirmed, 3: Completed, 4: Cancelled
        'row_version'
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'total_amount' => 'decimal:4',
    ];

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id', 'sales_order_id');
    }
}