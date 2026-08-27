<?php

namespace App\Modules\SaasPlatform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanFeature extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'plan_features';
    protected $primaryKey = 'plan_feature_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'plan_module_id',
        'code',
        'name',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'row_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function planModule(): BelongsTo
    {
        return $this->belongsTo(PlanModule::class, 'plan_module_id', 'plan_module_id');
    }

    public function versionFeatures(): HasMany
    {
        return $this->hasMany(PlanVersionFeature::class, 'plan_feature_id', 'plan_feature_id');
    }
}