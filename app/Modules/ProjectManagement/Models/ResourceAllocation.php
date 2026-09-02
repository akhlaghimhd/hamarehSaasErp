<?php

namespace App\Modules\ProjectManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResourceAllocation extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'resource_allocations';
    protected $primaryKey = 'allocation_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'task_id',
        'resource_type',
        'resource_id',
        'allocated_quantity',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'resource_type'      => 'integer',
        'allocated_quantity' => 'decimal:4',
        'start_date'         => 'date',
        'end_date'           => 'date',
        'row_version'        => 'integer',
    ];

    public const TYPE_HUMAN    = 1;
    public const TYPE_MACHINE  = 2;
    public const TYPE_MATERIAL = 3;
}
