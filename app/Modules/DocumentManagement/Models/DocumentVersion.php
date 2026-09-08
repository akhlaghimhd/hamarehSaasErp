<?php

namespace App\Modules\DocumentManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * document_versions — Version history of documents (Owner: DocumentManagement / Layer 5)
 * SoftDeletes + full audit fields required by Architecture Rules 1.4 & 3.5
 */
class DocumentVersion extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'document_versions';

    protected $primaryKey = 'version_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'document_id',
        'version_number',
        'attachment_id',
        'change_summary',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'row_version'    => 'integer',
            'created_at'     => 'datetime',
            'updated_at'     => 'datetime',
            'deleted_at'     => 'datetime',
        ];
    }
}
