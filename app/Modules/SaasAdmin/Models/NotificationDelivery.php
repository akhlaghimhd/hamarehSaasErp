<?php

namespace App\Modules\SaasAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    use HasUuids;

    protected $table = 'notification_deliveries';
    protected $primaryKey = 'delivery_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'notification_id', 'channel', 'status', 'error_message',
        'sent_at', 'delivered_at', 'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'retry_count' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
