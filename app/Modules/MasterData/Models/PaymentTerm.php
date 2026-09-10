<?php

namespace App\Modules\MasterData\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentTerm extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'payment_terms';

    protected $primaryKey = 'payment_term_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'net_days',
        'discount_days',
        'discount_percentage',
        'penalty_percentage_per_month',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'net_days' => 'integer',
        'discount_days' => 'integer',
        'discount_percentage' => 'decimal:4',
        'penalty_percentage_per_month' => 'decimal:4',
        'status' => 'integer',
    ];
}
