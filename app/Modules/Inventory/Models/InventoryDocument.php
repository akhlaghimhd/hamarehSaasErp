<?php

namespace App\Modules\Inventory\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * inv_documents — inventory transactional headers (Owner: Inventory)
 * Per Inventory_Logistics_Module.md
 * document_type: 1 Receipt, 2 Issue, 3 Transfer, 4 Cycle Adjustment
 * status: 1 Draft, 2 Pending Approval, 3 Posted, 4 Voided
 * fiscal_period_id / business_partner_id / source_document_* / accounting_voucher_id are logical UUIDs only.
 */
class InventoryDocument extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'inv_documents';

    protected $primaryKey = 'document_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'fiscal_period_id',
        'document_type',
        'document_number',
        'posting_date',
        'source_document_type',
        'source_document_id',
        'business_partner_id',
        'accounting_voucher_id',
        'status',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'document_type' => 'integer',
        'status'        => 'integer',
        'posting_date'  => 'datetime',
        'row_version'   => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InventoryDocumentItem::class, 'document_id', 'document_id');
    }
}
