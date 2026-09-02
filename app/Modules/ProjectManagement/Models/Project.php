<?php

namespace App\Modules\ProjectManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'projects';
    protected $primaryKey = 'project_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'project_code',
        'name',
        'description',
        'start_date',
        'end_date',
        'actual_end_date',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_end_date' => 'date',
        'status' => 'integer',
        'row_version' => 'integer',
    ];

    public const STATUS_CANCELLED = 0;
    public const STATUS_PLANNING = 1;
    public const STATUS_ACTIVE = 2;
    public const STATUS_ON_HOLD = 3;
    public const STATUS_COMPLETED = 4;
}
