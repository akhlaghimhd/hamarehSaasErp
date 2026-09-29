<?php

namespace App\Modules\IdentityCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantAccessCertCampaign extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'tenant_access_cert_campaigns';

    protected $primaryKey = 'campaign_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'campaign_id',
        'tenant_id',
        'code',
        'name',
        'description',
        'status',
        'due_at',
        'opened_at',
        'completed_at',
        'owner_user_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'due_at'       => 'datetime',
            'opened_at'    => 'datetime',
            'completed_at' => 'datetime',
            'row_version'  => 'integer',
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
            'deleted_at'   => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(TenantAccessCertItem::class, 'campaign_id', 'campaign_id');
    }
}
