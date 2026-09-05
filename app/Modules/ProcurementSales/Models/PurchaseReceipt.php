<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReceipt extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'purchase_receipts';
    protected $primaryKey = 'purchase_receipt_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'receipt_number',
        'id_purchase_order_source',
        'supplier_id',
        'warehouse_id',
        'receipt_date',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'receipt_date' => 'datetime',
        'status'       => 'integer',
        'row_version'  => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseReceiptItem::class, 'purchase_receipt_id', 'purchase_receipt_id');
    }
}
