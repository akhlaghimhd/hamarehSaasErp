<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class TenantSsoProvider extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_sso_providers';

    protected $primaryKey = 'sso_provider_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'sso_provider_id',
        'tenant_id',
        'code',
        'name',
        'protocol',
        'issuer',
        'authorization_endpoint',
        'token_endpoint',
        'jwks_uri',
        'client_id',
        'client_secret_encrypted',
        'scopes',
        'is_enabled',
        'auto_provision',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled'     => 'boolean',
            'auto_provision' => 'boolean',
            'row_version'    => 'integer',
            'created_at'     => 'datetime',
            'updated_at'     => 'datetime',
            'deleted_at'     => 'datetime',
        ];
    }

    public function setClientSecret(?string $plain): void
    {
        $this->client_secret_encrypted = $plain !== null && $plain !== ''
            ? Crypt::encryptString($plain)
            : null;
    }

    public function getClientSecret(): ?string
    {
        if ($this->client_secret_encrypted === null || $this->client_secret_encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($this->client_secret_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }
}
