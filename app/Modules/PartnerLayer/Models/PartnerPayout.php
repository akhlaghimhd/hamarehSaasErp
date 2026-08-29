<?php

namespace App\Modules\PartnerLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * currency_id and bank_account_id are logical / same-BC references (no cross-module FK).
 */
class PartnerPayout extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'partner_payouts';
    protected $primaryKey = 'payout_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'partner_id',
        'payout_number',
        'total_amount',
        'currency_id',
        'bank_account_id',
        'payout_date',
        'payment_reference',
        'status',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'total_amount' => 'decimal:4',
        'status'       => 'integer',
        'payout_date'  => 'datetime',
        'row_version'  => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'partner_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(PartnerBankAccount::class, 'bank_account_id', 'partner_bank_account_id');
    }
}
