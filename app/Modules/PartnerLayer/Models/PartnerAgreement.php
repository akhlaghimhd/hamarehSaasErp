<?php

namespace App\Modules\PartnerLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerAgreement extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'partner_agreements';
    protected $primaryKey = 'agreement_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'partner_id',
        'agreement_number',
        'agreement_type',
        'start_date',
        'end_date',
        'payment_cycle',
        'description',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'row_version',
    ];

    protected $casts = [
        'agreement_type' => 'integer',
        'payment_cycle'  => 'integer',
        'status'         => 'integer',
        'start_date'     => 'datetime',
        'end_date'       => 'datetime',
        'row_version'    => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'partner_id');
    }

    public function commissionRules(): HasMany
    {
        return $this->hasMany(PartnerCommissionRule::class, 'agreement_id', 'agreement_id');
    }
}
