<?php

namespace App\Modules\PartnerLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerBankAccount extends Model
{
    use HasUuids;

    protected $table = 'partner_bank_accounts';
    protected $primaryKey = 'partner_bank_account_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'partner_id',
        'bank_name',
        'account_number',
        'shaba_number',
        'card_number',
        'is_active',
        'row_version',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'row_version' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'partner_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(PartnerPayout::class, 'bank_account_id', 'partner_bank_account_id');
    }
}
