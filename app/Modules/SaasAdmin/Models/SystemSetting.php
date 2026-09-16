<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * system_settings — platform-wide key/value (Owner: SaaS Admin)
 */
class SystemSetting extends Model
{
    use HasUuids, SoftDeletes;

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
}
