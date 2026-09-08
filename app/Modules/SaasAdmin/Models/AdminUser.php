<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminUser extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'admin_users';

    protected $primaryKey = 'admin_user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'mobile',
        'status',
        'last_login_at',
        'failed_login_count',
        'locked_until',
        'two_factor_enabled',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'status'              => 'integer',
            'failed_login_count'  => 'integer',
            'two_factor_enabled'  => 'boolean',
            'row_version'         => 'integer',
            'last_login_at'       => 'datetime',
            'locked_until'        => 'datetime',
            'created_at'          => 'datetime',
            'updated_at'          => 'datetime',
            'deleted_at'          => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminRole::class,
            'admin_user_roles',
            'admin_user_id',
            'admin_role_id',
            'admin_user_id',
            'admin_role_id'
        )->wherePivotNull('deleted_at');
    }
}
