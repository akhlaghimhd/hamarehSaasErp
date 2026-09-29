<?php

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Platform master catalog for hierarchy purposes (D6).
 * No tenant_id — shared labels/types; SYS trees remain limited by product law.
 */
class HierarchyPurposeCatalog extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'erp_hierarchy_purpose_catalog';

    protected $primaryKey = 'purpose_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'label_fa',
        'label_en',
        'description',
        'is_system',
        'allows_user_tree',
        'is_active',
        'sort_order',
        'allowed_entity_types',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'allows_user_tree' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'allowed_entity_types' => 'array',
            'row_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
