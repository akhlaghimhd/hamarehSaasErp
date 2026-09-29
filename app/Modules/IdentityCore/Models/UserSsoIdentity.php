<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserSsoIdentity extends Model
{
    use SoftDeletes;

    protected $table = 'user_sso_identities';

    protected $primaryKey = 'sso_identity_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'sso_identity_id',
        'user_id',
        'tenant_id',
        'sso_provider_id',
        'provider_code',
        'external_subject',
        'external_email',
        'last_login_at',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'row_version'   => 'integer',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
            'deleted_at'    => 'datetime',
        ];
    }
}
