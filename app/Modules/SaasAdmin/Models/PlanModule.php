<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanModule extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'plan_modules';
    protected $primaryKey = 'plan_module_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'plan_version_id',
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

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class, 'plan_version_id', 'plan_version_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'plan_module_id', 'plan_module_id');
    }
}