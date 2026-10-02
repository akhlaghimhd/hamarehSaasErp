<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;

class TenantScopeMember extends Model
{
    use SoftDeletes, HasUuids, TenantScoped;

    protected $table = 'tenant_scope_members';
    protected $primaryKey = 'scope_member_id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'tenant_id',
        'scope_id',
        'reference_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'row_version' => 'integer',
    ];

    public function scope(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TenantScope::class, 'scope_id', 'scope_id');
    }
}
