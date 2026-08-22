<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLog extends Model
{
    use HasUuids, TenantScoped;

    protected $table = 'mfg_production_logs';
    
    protected $primaryKey = 'production_log_id';

    public $timestamps = false; // طبق مایگریشن فقط logged_at استفاده می‌شود

    protected $fillable = [
        'tenant_id',
        'production_order_id', // Physical FK
        'routing_id',          // Optional Physical FK
        'log_type',            // 1: Material Consumption, 2: Labor/Machine Time, 3: Scrap
        'item_id',             // Logical Reference to MasterData (inv_items)
        'quantity_consumed',
        'hours_spent',
        'logged_at'
    ];

    protected $casts = [
        'log_type' => 'integer',
        'quantity_consumed' => 'decimal:4',
        'hours_spent' => 'decimal:4',
        'logged_at' => 'datetime',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id', 'production_order_id');
    }
}