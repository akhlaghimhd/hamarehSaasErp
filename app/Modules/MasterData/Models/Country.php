<?php

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Country extends Model
{
    // حذف TenantScoped به دلیل Global بودن داده
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'countries';
    protected $primaryKey = 'country_id';
    public $incrementing = false;
    protected $keyType = 'string';

    // فاقد فیلدهای حسابرسی لاگ به دلیل استاتیک بودن دیتای گلوبال در مستندات
    const UPDATED_AT = null; 

    protected $fillable = [
        'iso_code',
        'iso_numeric_code',
        'name',
        'phone_code',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];
}