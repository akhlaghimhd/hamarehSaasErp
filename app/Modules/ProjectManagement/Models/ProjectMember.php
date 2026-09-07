<?php

namespace App\Modules\ProjectManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'project_members';
    protected $primaryKey = 'project_member_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'employee_id',
        'project_role',
        'joined_at',
        'left_at',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'joined_at'   => 'date',
        'left_at'     => 'date',
        'is_active'   => 'boolean',
        'row_version' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }
}
