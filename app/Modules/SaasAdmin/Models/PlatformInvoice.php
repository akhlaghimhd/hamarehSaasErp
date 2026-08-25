<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformInvoice extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'platform_invoices';
    protected $primaryKey = 'invoice_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'invoice_profile_id',
        'invoice_number',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'final_amount',
        'status',
        'issue_date',
        'due_date',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'final_amount' => 'decimal:4',
            'status' => 'integer',
            'issue_date' => 'datetime',
            'due_date' => 'datetime',
            'row_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlatformInvoiceItem::class, 'invoice_id', 'invoice_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PlatformTransaction::class, 'invoice_id', 'invoice_id');
    }
}