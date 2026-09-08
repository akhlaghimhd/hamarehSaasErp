<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'purchase_invoices';
    protected $primaryKey = 'purchase_invoice_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'invoice_number',
        'supplier_id',
        'purchase_order_id',
        'currency_id',
        'fiscal_period_id',
        'invoice_date',
        'due_date',
        'posting_date',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'status',
        'accounting_voucher_id',
        'tax_invoice_number',
        'supplier_invoice_ref',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'invoice_date'     => 'date',
        'due_date'         => 'date',
        'posting_date'     => 'date',
        'subtotal_amount'  => 'decimal:4',
        'discount_amount'  => 'decimal:4',
        'tax_amount'       => 'decimal:4',
        'total_amount'     => 'decimal:4',
        'status'           => 'integer',
        'row_version'      => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class, 'purchase_invoice_id', 'purchase_invoice_id');
    }
}
