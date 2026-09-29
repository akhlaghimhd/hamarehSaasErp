<?php

namespace App\Modules\SaasPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Platform Master Data — sellable feature packs (no tenant_id).
 */
class PlatformFeatureCatalog extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'platform_feature_catalog';
    protected $primaryKey = 'feature_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'feature_id',
        'code',
        'name',
        'description',
        'category',
        'is_sellable',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_sellable' => 'boolean',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
            'row_version' => 'integer',
        ];
    }
}
