<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenItemAllocation extends Model
{
    use HasUuids, TenantScoped;

    public $timestamps = false;

    protected $table = 'fin_acc_open_item_allocations';

    protected $primaryKey = 'allocation_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'open_item_id',
        'treasury_document_id',
        'journal_entry_id',
        'allocated_amount',
        'allocation_date',
        'description',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:4',
            'allocation_date'  => 'date',
            'created_at'       => 'datetime',
        ];
    }

    public function openItem(): BelongsTo
    {
        return $this->belongsTo(OpenItem::class, 'open_item_id', 'open_item_id');
    }
}
