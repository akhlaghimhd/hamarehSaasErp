<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnOrder extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'return_orders';
    protected $primaryKey = 'return_order_id';

    protected $fillable = [
        'tenant_id',
        'business_partner_id',
        'source_document_type', // e.g., 'SALES_DELIVERY', 'PURCHASE_RECEIPT'
        'source_document_id',
        'return_number',
        'return_date',
        'total_amount',
        'status', // 1: Pending, 2: Approved, 3: Completed, 4: Rejected
        'row_version'
    ];

    protected $casts = [
        'return_date' => 'date',
        'total_amount' => 'decimal:4',
    ];

    public function items()
    {
        return $this->hasMany(ReturnOrderItem::class, 'return_order_id', 'return_order_id');
    }
}