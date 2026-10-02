<?php

namespace App\Modules\IdentityCore\Models;

use App\Base\Services\HoldingAccessService;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * tenant_users — Tenant membership of a user (Owner: IdentityCore / Layer 4)
 * SoftDeletes + full audit fields required by Architecture Rules 1.4 & 3.5
 *
 * ADR-ID-ORG-003: global holding filter — actors with COMPANY scopes only see
 * memberships in their company circle; owner / no-COMPANY-scope actors unchanged.
 */
class TenantUser extends Model
{
    use HasUuids, HasFactory, TenantScoped, SoftDeletes;

    protected $table = 'tenant_users';

    protected $primaryKey = 'tenant_user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'employee_id',
        'is_owner',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'status'      => 'integer',
            'is_owner'    => 'boolean',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('holding_delegated_admin', function ($builder) {
            try {
                app(HoldingAccessService::class)->constrainTenantUsersQuery($builder);
            } catch (\Throwable) {
                // CLI / missing container: do not block tenant isolation queries
            }
        });

        static::created(function (TenantUser $tenantUser) {
            try {
                $svc = app(HoldingAccessService::class);
                $scopeIds = $svc->actorCompanyScopeIds();
                if ($scopeIds === []) {
                    return;
                }
                $tenantId = (string) $tenantUser->tenant_id;
                foreach ($scopeIds as $scopeId) {
                    $exists = DB::table('tenant_user_scopes')
                        ->where('tenant_id', $tenantId)
                        ->where('tenant_user_id', $tenantUser->tenant_user_id)
                        ->where('scope_id', $scopeId)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    DB::table('tenant_user_scopes')->insert([
                        'assignment_id'  => (string) Str::uuid(),
                        'tenant_id'      => $tenantId,
                        'tenant_user_id' => $tenantUser->tenant_user_id,
                        'scope_id'       => $scopeId,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                        'row_version'    => 1,
                    ]);
                }
            } catch (\Throwable) {
                // best-effort; member create must not fail solely on scope attach
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Modules\SaasPlatform\Models\Tenant::class, 'tenant_id', 'tenant_id');
    }

    protected static function newFactory()
    {
        return \Database\Factories\TenantUserFactory::new();
    }
}
