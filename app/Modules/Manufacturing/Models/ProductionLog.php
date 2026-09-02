<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLog extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'mfg_production_logs';
    protected $primaryKey = 'production_log_id';

    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'routing_id',
        'log_type',
        'item_id',
        'quantity_consumed',
        'hours_spent',
        'logged_at',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'log_type' => 'integer',
        'quantity_consumed' => 'decimal:4',
        'hours_spent' => 'decimal:4',
        'logged_at' => 'datetime',
        'row_version' => 'integer',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id', 'production_order_id');
    }
}
