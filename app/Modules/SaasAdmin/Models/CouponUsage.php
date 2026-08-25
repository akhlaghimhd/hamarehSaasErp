<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'coupon_usages';
    protected $primaryKey = 'coupon_usage_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'coupon_id',
        'tenant_id',
        'invoice_id',
        'discount_amount',
        'used_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:4',
            'used_at' => 'datetime',
            'row_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id', 'coupon_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PlatformInvoice::class, 'invoice_id', 'invoice_id');
    }
}