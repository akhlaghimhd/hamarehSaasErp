<?php

namespace App\Modules\DocumentManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * documents — Document Management core entity (Owner: DocumentManagement / Layer 5)
 * SoftDeletes + full audit fields required by Architecture Rules 1.4 & 3.5
 */
class Document extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'documents';

    protected $primaryKey = 'document_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'document_number',
        'title',
        'description',
        'document_type',
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
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }
}
