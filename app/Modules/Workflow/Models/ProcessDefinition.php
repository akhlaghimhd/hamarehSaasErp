<?php

namespace App\Modules\Workflow\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessDefinition extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'wf_process_definitions';
    protected $primaryKey = 'process_definition_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'target_aggregate_type',
        'flow_graph',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'flow_graph'  => 'array',
        'is_active'   => 'boolean',
        'row_version' => 'integer',
    ];

    public function instances(): HasMany
    {
        return $this->hasMany(ProcessInstance::class, 'process_definition_id', 'process_definition_id');
    }
}
