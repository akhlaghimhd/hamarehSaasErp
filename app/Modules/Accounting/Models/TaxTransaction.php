<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;

class TaxTransaction extends Model
{
    use HasUuids, TenantScoped;

    protected $table = 'fin_acc_tax_transactions';
    protected $primaryKey = 'transaction_id';

    protected $fillable = [
        'tenant_id',
        'transaction_date',
        'tax_type',
        'base_amount',
        'tax_amount',
        'tax_rate',
        'reference_document_type',
        'reference_document_id',
        'business_partner_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'base_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'tax_type' => 'integer',
    ];
}