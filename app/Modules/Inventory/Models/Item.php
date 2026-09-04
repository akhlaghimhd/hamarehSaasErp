<?php

namespace App\Modules\Inventory\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'items';
    
    protected $primaryKey = 'item_id';
    
    public $incrementing = false;
    
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'code',           // کد کالا یا SKU
        'name',           // نام کالا
        'item_type',      // 1: Raw Material, 2: Finished Product, 3: Service
        'base_uom',       // واحد اندازه گیری پایه (مثل PCS, KG)
        'status',         // 1: Active, 2: Inactive
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version'
    ];

    protected $casts = [
        'item_type' => 'integer',
        'status' => 'integer',
    ];
}
