<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntercompanyPartner extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'erp_intercompany_partners';

    protected $primaryKey = 'ic_partner_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'from_company_id',
        'to_company_id',
        'partner_customer_id',
        'partner_vendor_id',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'row_version' => 'integer',
            'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime',
        ];
    }

    public function fromCompany()
    {
        return $this->belongsTo(Company::class, 'from_company_id', 'company_id');
    }

    public function toCompany()
    {
        return $this->belongsTo(Company::class, 'to_company_id', 'company_id');
    }
}
