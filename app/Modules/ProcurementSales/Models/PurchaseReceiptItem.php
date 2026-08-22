<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceiptItem extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'purchase_receipt_items';
    protected $primaryKey = 'receipt_item_id';
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'receipt_id',
        'item_id', // UUID منطقی - اشاره به MasterData (کالا)
        'received_quantity',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'received_quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'total_price' => 'decimal:4',
    ];

    public function receipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'receipt_id', 'receipt_id');
    }
}