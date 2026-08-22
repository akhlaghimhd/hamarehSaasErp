<?php

namespace App\Modules\MasterData\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaxDefinition extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'tax_definitions';
    protected $primaryKey = 'tax_definition_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'tax_category_id',
        'code',
        'name',
        'tax_type',
        'calculation_type',
        'tax_rate',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version'
    ];

    protected $casts = [
        'tax_type' => 'integer',
        'calculation_type' => 'integer',
        'tax_rate' => 'decimal:4',
        'status' => 'integer',
    ];
}