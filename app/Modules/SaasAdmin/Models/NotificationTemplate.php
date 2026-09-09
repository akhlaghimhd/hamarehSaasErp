<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'notification_templates';
    protected $primaryKey = 'template_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'template_code',
        'title',
        'body_template',
        'channel',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }
}
