<?php

namespace App\Modules\SaasAdmin\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'support_tickets';

    protected $primaryKey = 'ticket_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'tenant_user_id',
        'assigned_admin_user_id',
        'ticket_number',
        'subject',
        'description',
        'priority',
        'status',
        'channel',
        'category_id',
        'closed_at',
        'resolved_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'priority'    => 'integer',
            'status'      => 'integer',
            'row_version' => 'integer',
            'closed_at'   => 'datetime',
            'resolved_at' => 'datetime',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }
}
