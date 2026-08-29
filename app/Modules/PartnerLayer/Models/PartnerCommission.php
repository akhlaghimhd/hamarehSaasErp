<?php

namespace App\Modules\PartnerLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * tenant_id, invoice_id, currency_id are logical references (no cross-module FK).
 */
class PartnerCommission extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'partner_commissions';
    protected $primaryKey = 'commission_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'partner_id',
        'tenant_id',
        'invoice_id',
        'commission_rule_id',
        'base_amount',
        'commission_type_snapshot',
        'commission_value_snapshot',
        'commission_amount',
        'currency_id',
        'exchange_rate',
        'status',
        'calculated_at',
        'paid_at',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'base_amount'                 => 'decimal:4',
        'commission_type_snapshot'    => 'integer',
        'commission_value_snapshot'   => 'decimal:4',
        'commission_amount'           => 'decimal:4',
        'exchange_rate'               => 'decimal:8',
        'status'                      => 'integer',
        'calculated_at'               => 'datetime',
        'paid_at'                     => 'datetime',
        'row_version'                 => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'partner_id');
    }

    public function commissionRule(): BelongsTo
    {
        return $this->belongsTo(PartnerCommissionRule::class, 'commission_rule_id', 'commission_rule_id');
    }
}
