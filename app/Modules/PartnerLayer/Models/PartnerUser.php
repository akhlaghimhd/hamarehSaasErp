<?php

namespace App\Modules\PartnerLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a Partner to an IdentityCore user (logical user_id — no cross-module FK).
 */
class PartnerUser extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'partner_users';
    protected $primaryKey = 'partner_user_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'partner_id',
        'user_id',
        'is_primary',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'is_primary'  => 'boolean',
        'status'      => 'integer',
        'row_version' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'partner_id');
    }
}
