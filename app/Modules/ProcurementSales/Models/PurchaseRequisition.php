<?php

namespace App\Modules\ProcurementSales\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequisition extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'purchase_requisitions';
    protected $primaryKey = 'requisition_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'department_id', 'requisition_number', 'required_date',
        'priority', 'status', 'description',
        'created_by', 'updated_by', 'deleted_by', 'row_version',
    ];

    protected $casts = [
        'required_date' => 'date',
        'priority' => 'integer',
        'status' => 'integer',
        'row_version' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseRequisitionItem::class, 'requisition_id', 'requisition_id');
    }
}
