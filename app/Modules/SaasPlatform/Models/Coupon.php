<?php

namespace App\Modules\SaasPlatform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'coupons';
    protected $primaryKey = 'coupon_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'status',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => 'integer',
            'discount_value' => 'decimal:4',
            'status' => 'integer',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'row_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class, 'coupon_id', 'coupon_id');
    }
}