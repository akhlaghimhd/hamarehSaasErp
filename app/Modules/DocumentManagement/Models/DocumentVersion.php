<?php

namespace App\Modules\DocumentManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentVersion extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'document_versions';
    protected $primaryKey = 'version_id';

    protected $fillable = [
        'tenant_id', 'document_id', 'version_number', 
        'attachment_id', 'change_summary', 'row_version'
    ];
}