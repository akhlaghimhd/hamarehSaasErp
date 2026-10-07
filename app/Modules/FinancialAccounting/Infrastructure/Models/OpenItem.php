<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpenItem extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    public const SIDE_AR = 'AR';
    public const SIDE_AP = 'AP';

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_PARTIAL = 'PARTIAL';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_VOID = 'VOID';

    protected $table = 'fin_acc_open_items';

    protected $primaryKey = 'open_item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'side',
        'document_type',
        'document_number',
        'document_date',
        'due_date',
        'counterparty_name',
        'counterparty_ref_id',
        'original_amount',
        'open_amount',
        'currency_id',
        'gl_account_id',
        'journal_entry_id',
        'status',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'document_date'   => 'date',
            'due_date'        => 'date',
            'original_amount' => 'decimal:4',
            'open_amount'     => 'decimal:4',
            'row_version'     => 'integer',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
            'deleted_at'      => 'datetime',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OpenItemAllocation::class, 'open_item_id', 'open_item_id');
    }
}
