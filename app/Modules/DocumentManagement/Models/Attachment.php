<?php

namespace App\Modules\DocumentManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * attachments — Polymorphic file attachments (Owner: DocumentManagement / Layer 5)
 * SoftDeletes + full audit fields required by Architecture Rules 1.4 & 3.5
 */
class Attachment extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'attachments';

    protected $primaryKey = 'attachment_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'target_entity_type',
        'target_entity_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size_bytes',
        'file_hash',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'row_version'     => 'integer',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
            'deleted_at'      => 'datetime',
        ];
    }
}
