<?php

namespace App\Modules\MasterData\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UnitOfMeasure extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'units_of_measure';
    protected $primaryKey = 'uom_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'decimal_places',
        'conversion_factor',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version'
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'conversion_factor' => 'decimal:4',
        'status' => 'integer',
    ];
}