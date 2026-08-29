<?php

namespace App\Modules\PartnerLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerContact extends Model
{
    use HasUuids;

    protected $table = 'partner_contacts';
    protected $primaryKey = 'partner_contact_id';

    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'partner_id',
        'first_name',
        'last_name',
        'role_title',
        'email',
        'phone_number',
        'is_primary',
        'row_version',
    ];

    protected $casts = [
        'is_primary'  => 'boolean',
        'row_version' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'partner_id');
    }
}
