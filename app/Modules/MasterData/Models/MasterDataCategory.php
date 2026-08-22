<?php

namespace App\Modules\MasterData\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MasterDataCategory extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'master_data_categories';
    protected $primaryKey = 'master_data_category_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'code', 'name', 'description', 
        'is_system_category', 'status',
        'created_by', 'updated_by', 'deleted_by', 'row_version'
    ];

    protected $casts = [
        'is_system_category' => 'boolean',
        'status' => 'integer',
    ];
}