<?php

namespace App\Modules\PartnerLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerCommissionRule extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'partner_commission_rules';
    protected $primaryKey = 'commission_rule_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'agreement_id',
        'revenue_type',
        'commission_type',
        'commission_value',
        'calculation_basis',
        'minimum_amount',
        'maximum_amount',
        'effective_from',
        'effective_to',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'revenue_type'      => 'integer',
        'commission_type'   => 'integer',
        'commission_value'  => 'decimal:4',
        'calculation_basis' => 'integer',
        'minimum_amount'    => 'decimal:4',
        'maximum_amount'    => 'decimal:4',
        'effective_from'    => 'datetime',
        'effective_to'      => 'datetime',
        'status'            => 'integer',
        'row_version'       => 'integer',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(PartnerAgreement::class, 'agreement_id', 'agreement_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(PartnerCommission::class, 'commission_rule_id', 'commission_rule_id');
    }
}
