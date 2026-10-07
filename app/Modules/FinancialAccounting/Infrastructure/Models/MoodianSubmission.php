<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Models;

use App\Base\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MoodianSubmission extends Model
{
    use HasUuids, TenantScoped;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_ACCEPTED = 'ACCEPTED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'fin_acc_moodian_submissions';

    protected $primaryKey = 'moodian_submission_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'source_document_type',
        'source_document_id',
        'tax_transaction_id',
        'external_ref',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'submitted_at',
        'last_polled_at',
        'created_by',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at'  => 'datetime',
            'last_polled_at'=> 'datetime',
            'row_version'   => 'integer',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
        ];
    }
}
