<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferAvailableAddon extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'offer_available_addons';
    protected $primaryKey = 'offer_available_addon_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'plan_offer_id',
        'addon_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
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

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class, 'addon_id', 'addon_id');
    }
}