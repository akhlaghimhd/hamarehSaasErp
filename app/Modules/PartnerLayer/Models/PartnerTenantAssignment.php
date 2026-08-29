<?php

namespace App\Modules\PartnerLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assigns a tenant (logical tenant_id) to a partner.
 */
class PartnerTenantAssignment extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'partner_tenant_assignments';
    protected $primaryKey = 'assignment_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'partner_id',
        'tenant_id',
        'assignment_type',
        'start_date',
        'end_date',
        'transfer_reason',
        'assigned_by',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'assignment_type' => 'integer',
        'status'          => 'integer',
        'start_date'      => 'datetime',
        'end_date'        => 'datetime',
        'row_version'     => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'partner_id');
    }
}
