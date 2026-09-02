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
    protected $primaryKey = 'receipt_item_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'receipt_id',
        'item_id',
        'received_quantity',
        'unit_price',
        'total_price',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'received_quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'total_price' => 'decimal:4',
        'row_version' => 'integer',
    ];

    public function receipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'receipt_id', 'receipt_id');
    }
}
