<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    public const TYPE_ASSET = 1;
    public const TYPE_LIABILITY = 2;
    public const TYPE_EQUITY = 3;
    public const TYPE_REVENUE = 4;
    public const TYPE_EXPENSE = 5;

    public const BALANCE_DEBIT = 1;
    public const BALANCE_CREDIT = 2;

    protected $table = 'fin_acc_accounts';

    protected $primaryKey = 'account_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'parent_account_id',
        'account_code',
        'name',
        'account_type',
        'account_level',
        'normal_balance',
        'is_control_account',
        'is_postable',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'account_type'       => 'integer',
            'account_level'      => 'integer',
            'normal_balance'     => 'integer',
            'is_control_account' => 'boolean',
            'is_postable'        => 'boolean',
            'status'             => 'integer',
            'row_version'        => 'integer',
            'created_at'         => 'datetime',
            'updated_at'         => 'datetime',
            'deleted_at'         => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_account_id', 'account_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_account_id', 'account_id');
    }

    public function journalItems(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'account_id', 'account_id');
    }
}
