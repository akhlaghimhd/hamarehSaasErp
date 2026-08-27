<?php

namespace App\Modules\SaasPlatform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanOfferDiscount extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'plan_offer_discounts';
    protected $primaryKey = 'plan_offer_discount_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'plan_offer_id',
        'discount_value',
        'discount_type',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:4',
            'discount_type' => 'integer',
            'row_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function planOffer(): BelongsTo
    {
        return $this->belongsTo(PlanOffer::class, 'plan_offer_id', 'plan_offer_id');
    }
}