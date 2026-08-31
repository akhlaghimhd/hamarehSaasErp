<?php

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Platform Master Data — no tenant_id (shared across tenants).
 * Schema: database/migrations/master_data/2026_08_02_145413_create_currencies_table.php
 */
class Currency extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'currencies';
    protected $primaryKey = 'currency_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'is_default',
        'status',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'status'     => 'boolean',
    ];
}
