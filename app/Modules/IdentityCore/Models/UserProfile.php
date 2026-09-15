<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'user_profiles';
    protected $primaryKey = 'profile_id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /** address_change_status */
    public const ADDRESS_STATUS_NONE = 0;
    public const ADDRESS_STATUS_PENDING = 1;
    public const ADDRESS_STATUS_APPROVED = 2;
    public const ADDRESS_STATUS_REJECTED = 3;

    protected $fillable = [
        'user_id',
        'national_id',
        'birth_date',
        'avatar_url',
        'gender',
        'address',
        'pending_address',
        'address_change_status',
        'phone',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'gender' => 'integer',
        'address_change_status' => 'integer',
        'row_version' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
