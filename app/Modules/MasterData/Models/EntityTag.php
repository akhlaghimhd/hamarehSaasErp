<?php

namespace App\Modules\MasterData\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EntityTag extends Model
{
    // جدول اتصالی (Pivot) فاقد SoftDeletes در اسناد معماری است
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'entity_tags';
    protected $primaryKey = 'entity_tag_id';
    public $incrementing = false;
    protected $keyType = 'string';

    const UPDATED_AT = null; // جلوگیری از خطای لاراول، زیرا فقط assigned_at داریم

    protected $fillable = [
        'tenant_id', 'tag_id', 'target_entity_type', 'target_entity_id',
        'assigned_at', 'assigned_by'
    ];
}