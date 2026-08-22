<?php

namespace App\Modules\DocumentManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'documents';
    protected $primaryKey = 'document_id';

    protected $fillable = [
        'tenant_id', 'document_number', 'title', 'description', 
        'document_type', 'status', 'row_version'
    ];
}