<?php

namespace App\Modules\MasterData\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EntityContactPoint extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'entity_contact_points';
    protected $primaryKey = 'contact_point_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'entity_type', 'entity_id', 'contact_type', 
        'contact_value', 'extension', 'is_primary', 'status',
        'created_by', 'updated_by', 'deleted_by', 'row_version'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'status' => 'integer',
    ];
}