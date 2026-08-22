<?php

namespace App\Modules\MasterData\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MasterDataValue extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'master_data_values';
    protected $primaryKey = 'master_data_value_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'master_data_category_id', 'parent_master_data_value_id',
        'code', 'name', 'sort_order', 'extra_data', 'status',
        'created_by', 'updated_by', 'deleted_by', 'row_version'
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'extra_data' => 'array', // کست کردن فیلد jsonb به array در لاراول
        'status' => 'integer',
    ];
}