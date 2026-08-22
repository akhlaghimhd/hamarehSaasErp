<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Base\Traits\TenantScoped;

class TenantUser extends Model
{
    use HasUuids, TenantScoped;

    protected $table = 'tenant_users';
    protected $primaryKey = 'tenant_user_id';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'employee_id',
        'is_owner',
        'status', // 1: Active, 2: Inactive
    ];

    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
            'status'   => 'integer',
        ];
    }
}