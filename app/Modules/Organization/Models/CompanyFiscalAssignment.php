<?php

namespace App\Modules\Organization\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * erp_company_fiscal_assignments — ORG-P2-02
 * Logical link company → fin_fiscal_periods.period_id (no physical FK).
 */
class CompanyFiscalAssignment extends Model
{
    use HasUuids, TenantScoped, SoftDeletes;

    protected $table = 'erp_company_fiscal_assignments';

    protected $primaryKey = 'assignment_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'period_id',
        'is_primary',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'is_primary'  => 'boolean',
            'status'      => 'integer',
            'row_version' => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'deleted_at'  => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
}
