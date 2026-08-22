<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesQuotationItem extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'sales_quotation_items';
    protected $primaryKey = 'quotation_item_id';
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'quotation_id',
        'item_id',
        'quantity',
        'unit_price',
        'total_price',
    ];

    public function quotation()
    {
        return $this->belongsTo(SalesQuotation::class, 'quotation_id', 'quotation_id');
    }
}