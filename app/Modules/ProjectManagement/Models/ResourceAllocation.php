<?php

namespace App\Modules\ProjectManagement\Models;

use App\Base\Models\BaseModel;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResourceAllocation extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'resource_allocations';
    protected $primaryKey = 'allocation_id';
    
    public $timestamps = false; // Based on your DB schema, no updated_at/created_at specified

    protected $fillable = [
        'tenant_id',
        'task_id',
        'resource_type',
        'resource_id',
        'allocated_quantity',
        'start_date',
        'end_date'
    ];

    protected $casts = [
        'resource_type'      => 'integer',
        'allocated_quantity' => 'decimal:4',
        'start_date'         => 'date',
        'end_date'           => 'date'
    ];

    // Resource Type Constants (Polymorphic Logical Reference)
    public const TYPE_HUMAN    = 1; // Maps to HrManagement -> employees
    public const TYPE_MACHINE  = 2; // Maps to Manufacturing -> mfg_work_centers
    public const TYPE_MATERIAL = 3; // Maps to MasterData -> inv_items
}