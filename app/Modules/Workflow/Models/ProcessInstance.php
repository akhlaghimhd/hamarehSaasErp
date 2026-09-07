<?php

namespace App\Modules\Workflow\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessInstance extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'wf_process_instances';
    protected $primaryKey = 'process_instance_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'process_definition_id',
        'target_aggregate_id',
        'target_aggregate_type',
        'current_state',
        'owning_tenant_id',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'status'      => 'integer',
        'row_version' => 'integer',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ProcessDefinition::class, 'process_definition_id', 'process_definition_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'process_instance_id', 'process_instance_id');
    }
}
