<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesQuotation extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'sales_quotations';
    protected $primaryKey = 'quotation_id';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'quotation_number',
        'quotation_date',
        'valid_until',
        'total_amount',
        'status', // 1: Draft, 2: Sent, 3: Accepted, 4: Rejected
        'row_version'
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until' => 'date',
        'total_amount' => 'decimal:4',
    ];

    public function items()
    {
        return $this->hasMany(SalesQuotationItem::class, 'quotation_id', 'quotation_id');
    }
}