<?php

namespace App\Modules\Workflow\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'wf_tasks';
    protected $primaryKey = 'task_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'process_instance_id',
        'assigned_type',
        'assigned_to_id',
        'task_name',
        'status',
        'context_snapshots',
        'actioned_at',
        'actioned_by',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'assigned_type'      => 'integer',
        'status'             => 'integer',
        'context_snapshots'  => 'array',
        'actioned_at'        => 'datetime',
        'row_version'        => 'integer',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class, 'process_instance_id', 'process_instance_id');
    }
}
