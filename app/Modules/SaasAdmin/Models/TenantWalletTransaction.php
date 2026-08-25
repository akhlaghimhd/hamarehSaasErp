<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantWalletTransaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tenant_wallet_transactions';
    protected $primaryKey = 'wallet_transaction_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'wallet_id',
        'transaction_type',
        'amount',
        'balance_after',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => 'integer',
            'amount' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'row_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(TenantWallet::class, 'wallet_id', 'wallet_id');
    }
}