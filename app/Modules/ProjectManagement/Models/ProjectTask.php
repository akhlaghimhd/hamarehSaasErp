<?php

namespace App\Modules\ProjectManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'project_tasks';
    protected $primaryKey = 'task_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'parent_task_id',
        'task_code',
        'title',
        'description',
        'status',
        'priority',
        'start_date',
        'due_date',
        'actual_end_date',
        'estimated_hours',
        'actual_hours',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'due_date'         => 'date',
        'actual_end_date'  => 'date',
        'status'           => 'integer',
        'priority'         => 'integer',
        'estimated_hours'  => 'decimal:4',
        'actual_hours'     => 'decimal:4',
        'row_version'      => 'integer',
    ];

    public const STATUS_TODO = 1;
    public const STATUS_IN_PROGRESS = 2;
    public const STATUS_REVIEW = 3;
    public const STATUS_DONE = 4;

    public const PRIORITY_LOW = 1;
    public const PRIORITY_MEDIUM = 2;
    public const PRIORITY_HIGH = 3;
    public const PRIORITY_CRITICAL = 4;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }
}
