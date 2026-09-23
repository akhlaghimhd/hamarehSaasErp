<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntercompanyRule extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'erp_intercompany_rules';

    protected $primaryKey = 'ic_rule_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'source_doc_type',
        'target_doc_type',
        'auto_create_mirror',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'auto_create_mirror' => 'boolean',
            'is_active' => 'boolean',
            'row_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
