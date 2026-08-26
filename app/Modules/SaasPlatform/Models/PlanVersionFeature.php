<?php

namespace App\Modules\SaasPlatform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanVersionFeature extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'plan_version_features';
    protected $primaryKey = 'plan_version_feature_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'plan_version_id',
        'plan_feature_id',
        'enabled',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
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

    public function planFeature(): BelongsTo
    {
        return $this->belongsTo(PlanFeature::class, 'plan_feature_id', 'plan_feature_id');
    }
}