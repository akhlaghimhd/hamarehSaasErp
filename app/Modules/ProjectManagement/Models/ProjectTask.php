<?php

namespace App\Modules\ProjectManagement\Models;

use App\Base\Models\BaseModel;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTask extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'project_tasks';
    protected $primaryKey = 'task_id';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'parent_task_id',
        'title',
        'description',
        'status',
        'priority',
        'start_date',
        'due_date',
        'row_version'
    ];

    protected $casts = [
        'start_date'  => 'date',
        'due_date'    => 'date',
        'status'      => 'integer',
        'priority'    => 'integer',
        'row_version' => 'integer'
    ];

    // Status Constants
    public const STATUS_TODO = 1;
    public const STATUS_IN_PROGRESS = 2;
    public const STATUS_REVIEW = 3;
    public const STATUS_DONE = 4;

    // Priority Constants
    public const PRIORITY_LOW = 1;
    public const PRIORITY_MEDIUM = 2;
    public const PRIORITY_HIGH = 3;
    public const PRIORITY_CRITICAL = 4;
}