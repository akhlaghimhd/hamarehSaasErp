<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReceiptItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'purchase_receipt_items';
    protected $primaryKey = 'purchase_receipt_item_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'purchase_receipt_id',
        'purchase_order_item_id',
        'item_id',
        'ordered_quantity',
        'received_quantity',
        'unit_price',
        'total_price',
        'uom_code',
        'line_number',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'ordered_quantity'  => 'decimal:4',
        'received_quantity' => 'decimal:4',
        'unit_price'        => 'decimal:4',
        'total_price'       => 'decimal:4',
        'line_number'       => 'integer',
        'row_version'       => 'integer',
    ];

    public function receipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id', 'purchase_receipt_id');
    }
}
