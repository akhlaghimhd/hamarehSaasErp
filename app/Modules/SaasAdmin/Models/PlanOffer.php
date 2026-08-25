<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanOffer extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'plan_offers';
    protected $primaryKey = 'plan_offer_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'plan_version_id',
        'name',
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
            'status' => 'integer',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'row_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class, 'plan_version_id', 'plan_version_id');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(PlanOfferDiscount::class, 'plan_offer_id', 'plan_offer_id');
    }

    public function availableAddons(): HasMany
    {
        return $this->hasMany(OfferAvailableAddon::class, 'plan_offer_id', 'plan_offer_id');
    }
}