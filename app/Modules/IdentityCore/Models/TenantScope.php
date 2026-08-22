<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;

class TenantScope extends Model
{
    use SoftDeletes, HasUuids, TenantScoped;

    protected $table = 'tenant_scopes';
    protected $primaryKey = 'scope_id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'tenant_id',
        'scope_name',
        'scope_type',
        'reference_id',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'row_version' => 'integer',
    ];

    /**
     * کاربرانی که به این محدوده دسترسی دارند
     */
    public function userAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TenantUserScope::class, 'scope_id', 'scope_id');
    }
}
