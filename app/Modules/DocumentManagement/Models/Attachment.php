<?php

namespace App\Modules\DocumentManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'attachments';
    protected $primaryKey = 'attachment_id';

    protected $fillable = [
        'tenant_id', 'target_entity_type', 'target_entity_id', 
        'file_name', 'file_path', 'mime_type', 'file_size_bytes', 
        'file_hash', 'row_version'
    ];
}