<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\TenantScoped;

class Account extends Model
{
    use HasUuids, SoftDeletes, TenantScoped;

    protected $table = 'fin_accounts';
    protected $primaryKey = 'account_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'parent_account_id',
        'code',
        'name',
        'account_type',
        'level',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'account_type' => 'integer',
        'level' => 'integer',
        'is_active' => 'boolean',
        'row_version' => 'integer',
    ];

    // ارتباط درختی با پدر
    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_account_id', 'account_id');
    }

    // ارتباط درختی با فرزندان
    public function children()
    {
        return $this->hasMany(Account::class, 'parent_account_id', 'account_id');
    }
}
