<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cheque extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    public const DIR_IN = 'IN';
    public const DIR_OUT = 'OUT';

    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_ISSUED = 'ISSUED';
    public const STATUS_DEPOSITED = 'DEPOSITED';
    public const STATUS_CLEARED = 'CLEARED';
    public const STATUS_BOUNCED = 'BOUNCED';
    public const STATUS_CANCELLED = 'CANCELLED';

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        self::STATUS_RECEIVED => [self::STATUS_DEPOSITED, self::STATUS_CANCELLED],
        self::STATUS_ISSUED => [self::STATUS_CLEARED, self::STATUS_BOUNCED, self::STATUS_CANCELLED],
        self::STATUS_DEPOSITED => [self::STATUS_CLEARED, self::STATUS_BOUNCED],
        self::STATUS_CLEARED => [],
        self::STATUS_BOUNCED => [self::STATUS_CANCELLED],
        self::STATUS_CANCELLED => [],
    ];

    protected $table = 'fin_acc_cheques';

    protected $primaryKey = 'cheque_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'direction',
        'cheque_number',
        'bank_name',
        'issue_date',
        'due_date',
        'amount',
        'currency_id',
        'payee_name',
        'drawer_name',
        'status',
        'cash_account_id',
        'treasury_document_id',
        'open_item_id',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'  => 'date',
            'due_date'    => 'date',
            'amount'      => 'decimal:4',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }
}
