<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * system_settings — platform-wide key/value (Owner: SaaS Admin)
 *
 * Tenant policy (e.g. dual role-approval) lives in tenant_settings via
 * TenantIdentitySettingsService — not here.
 */
class SystemSetting extends Model
{
    use HasUuids, SoftDeletes;

    public const KEY_PLATFORM_EMAIL_BASE_DOMAIN = 'platform_email_base_domain';

    public const KEY_RETENTION_SOFT_DELETE_DAYS_DEFAULT = 'retention.soft_delete_days_default';

    public const KEY_RETENTION_SOFT_DELETE_DAYS_ORG_MASTERS = 'retention.soft_delete_days_org_masters';

    public const KEY_RETENTION_PURGE_JOB_ENABLED = 'retention.purge_job_enabled';

    /**
     * Catalog of known platform keys with defaults (SAASADM-P3).
     * Used by seeder and ensureDefaults().
     *
     * @return array<string, array{value: string, description: string}>
     */
    public static function catalogDefaults(): array
    {
        return [
            self::KEY_PLATFORM_EMAIL_BASE_DOMAIN => [
                'value'       => 'erp.ir',
                'description' => 'Public platform base domain for organizational emails when tenant has no custom primary domain',
            ],
            self::KEY_RETENTION_SOFT_DELETE_DAYS_DEFAULT => [
                'value'       => '90',
                'description' => 'Default soft-delete retention days before physical purge job may run (platform default; tenant override later)',
            ],
            self::KEY_RETENTION_SOFT_DELETE_DAYS_ORG_MASTERS => [
                'value'       => '90',
                'description' => 'Retention days for Organization masters (non-primary company, branch, department) before purge',
            ],
            self::KEY_RETENTION_PURGE_JOB_ENABLED => [
                'value'       => 'false',
                'description' => 'When true, scheduled erp:purge-soft-deleted may physically delete past-retention rows (still referential-guarded)',
            ],
        ];
    }

    protected $table = 'system_settings';

    protected $primaryKey = 'system_setting_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'setting_key',
        'setting_value',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $row = static::query()
            ->where('setting_key', $key)
            ->whereNull('deleted_at')
            ->first();

        if (!$row || $row->setting_value === null || $row->setting_value === '') {
            return $default;
        }

        return (string) $row->setting_value;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $raw = static::getValue($key);
        if ($raw === null) {
            return $default;
        }

        $v = strtolower(trim($raw));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $raw = static::getValue($key);
        if ($raw === null || ! is_numeric($raw)) {
            return $default;
        }

        return (int) $raw;
    }
}
