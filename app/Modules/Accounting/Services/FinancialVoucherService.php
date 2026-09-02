<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTOs\FinancialVoucherDTO;
use App\Modules\Accounting\Models\FinancialVoucher;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancialVoucherService
{
    public function createVoucher(FinancialVoucherDTO $dto): FinancialVoucher
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $voucher = FinancialVoucher::create([
                'tenant_id'        => $tenantId,
                'voucher_date'     => $dto->voucherDate,
                'description'      => $dto->description,
                'total_amount'     => $dto->totalAmount,
                'reference_number' => $dto->referenceNumber,
                'currency_id'      => $dto->currencyId,
                'status'           => 1, // 1 = Draft
                'created_by'       => $userId,
                'row_version'      => 1,
            ]);

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_vouchers',
                'aggregate_id'   => $voucher->voucher_id,
                'event_type'     => 'accounting.voucher.created.v1',
                'payload'        => json_encode([
                    'voucher_id'       => $voucher->voucher_id,
                    'reference_number' => $voucher->reference_number,
                    'total_amount'     => $voucher->total_amount,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $voucher;
        });
    }
}
