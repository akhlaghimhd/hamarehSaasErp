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

    protected $fillable = [
        'tenant_id',
        'supplier_id', // UUID منطقی - اشاره به MasterData (BusinessPartner)
        'order_number',
        'order_date',
        'expected_delivery_date',
        'total_amount',
        'status', // 1: Draft, 2: Approved, 3: Completed, 4: Cancelled
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'total_amount' => 'decimal:4',
    ];

    // ارتباط درون ماژولی (مجاز)
    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id', 'purchase_order_id');
    }
}