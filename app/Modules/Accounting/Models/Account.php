<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Base\Traits\TenantScoped;

class Account extends Model
{
    use HasUuids, TenantScoped;

    protected $table = 'fin_accounts';
    protected $primaryKey = 'account_id';

    protected $fillable = [
        'tenant_id',
        'parent_account_id',
        'code',
        'name',
        'account_type',
        'level', // سطح در درخت (مثلاً 1=گروه، 2=کل، 3=معین)
        'description',
        'is_active',
    ];

    protected $casts = [
        'account_type' => 'integer',
        'level' => 'integer',
        'is_active' => 'boolean',
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