<?php

namespace App\Modules\DocumentManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

class DocumentSequence extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'document_sequences';
    protected $primaryKey = 'sequence_id';

    protected $fillable = [
        'tenant_id', 'company_id', 'module_code', 'document_type',
        'document_scope', 'owner_type', 'owner_id', 'prefix',
        'suffix', 'padding_length', 'current_value', 'reset_period',
        'last_reset_at', 'is_active', 'row_version',
        'created_by', 'updated_by', 'deleted_by'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'last_reset_at' => 'datetime',
    ];
}
