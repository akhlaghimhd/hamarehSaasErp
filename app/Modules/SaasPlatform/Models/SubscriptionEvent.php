<?php

namespace App\Modules\SaasPlatform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'subscription_events';
    protected $primaryKey = 'subscription_event_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'subscription_id',
        'event_type',
        'description',
        'event_date',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => 'integer',
            'event_date' => 'datetime',
            'row_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'subscription_id');
    }
}