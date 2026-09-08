<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminRole extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'admin_roles';

    protected $primaryKey = 'admin_role_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'status'      => 'integer',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminPermission::class,
            'admin_role_permissions',
            'admin_role_id',
            'admin_permission_id',
            'admin_role_id',
            'admin_permission_id'
        )->wherePivotNull('deleted_at');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminUser::class,
            'admin_user_roles',
            'admin_role_id',
            'admin_user_id',
            'admin_role_id',
            'admin_user_id'
        )->wherePivotNull('deleted_at');
    }
}
