<?php

namespace App\Modules\PartnerLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * verified_by is a logical reference to an admin/user (no cross-module FK).
 */
class PartnerDocument extends Model
{
    use HasUuids;

    protected $table = 'partner_documents';
    protected $primaryKey = 'partner_document_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'partner_id',
        'document_type',
        'document_number',
        'storage_path',
        'status',
        'verified_at',
        'verified_by',
        'row_version',
    ];

    protected $casts = [
        'status'      => 'integer',
        'verified_at' => 'datetime',
        'row_version' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'partner_id');
    }
}
