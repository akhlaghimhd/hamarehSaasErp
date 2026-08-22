<?php

namespace App\Modules\ProjectManagement\Models;

use App\Base\Models\BaseModel;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectMember extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'project_members';
    protected $primaryKey = 'member_id';

    public $timestamps = false; // We manage timestamps manually in DB schema or explicitly

    protected $fillable = [
        'tenant_id',
        'project_id',
        'employee_id', // Logical Reference to HrManagement -> employees
        'project_role',
        'joined_at',
        'left_at',
        'is_active',
        'created_at'
    ];

    protected $casts = [
        'joined_at' => 'date',
        'left_at' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime'
    ];
}