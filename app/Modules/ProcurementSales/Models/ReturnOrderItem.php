<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnOrderItem extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'return_order_items';
    protected $primaryKey = 'return_item_id';
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'return_order_id',
        'item_id',
        'quantity',
        'unit_price',
        'total_price',
    ];

    public function returnOrder()
    {
        return $this->belongsTo(ReturnOrder::class, 'return_order_id', 'return_order_id');
    }
}