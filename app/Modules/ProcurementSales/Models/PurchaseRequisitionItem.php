<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequisitionItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'purchase_requisition_items';
    protected $primaryKey = 'requisition_item_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'requisition_id', 'item_id', 'quantity', 'estimated_unit_price',
        'uom_code', 'line_number', 'description',
        'created_by', 'updated_by', 'deleted_by', 'row_version',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'estimated_unit_price' => 'decimal:4',
        'line_number' => 'integer',
        'row_version' => 'integer',
    ];

    public function requisition()
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id', 'requisition_id');
    }
}
