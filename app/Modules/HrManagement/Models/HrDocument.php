<?php

namespace App\Modules\HrManagement\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrDocument extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'hr_documents';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'document_type_code',
        'document_title',
        'issue_date',
        'expiry_date',
        'attachment_id',
        'status',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'status' => 'integer',
    ];

    /**
     * ارتباط منطقی و فیزیکی درون‌ماژولی با کارمند
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}