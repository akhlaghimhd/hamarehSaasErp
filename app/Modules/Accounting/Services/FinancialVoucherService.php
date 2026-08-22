<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTOs\FinancialVoucherDTO;
use App\Modules\Accounting\Models\FinancialVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancialVoucherService
{
    public function createVoucher(FinancialVoucherDTO $dto): FinancialVoucher
    {
        return DB::transaction(function () use ($dto) {
            // ۱. ثبت سند حسابداری
            $voucher = FinancialVoucher::create([
                // tenant_id به صورت خودکار توسط TenantScoped یا لایه Context پر می‌شود
                // در اینجا فرض بر این است که سیستم در کانتکست مستأجر قرار دارد
                'tenant_id' => app('current_tenant_id'), 
                'voucher_date' => $dto->voucherDate,
                'description' => $dto->description,
                'total_amount' => $dto->totalAmount,
                'reference_number' => $dto->referenceNumber,
                'currency_id' => $dto->currencyId,
                'status' => 1, // 1 = Draft (وضعیت پیش‌فرض)
            ]);

            // ۲. ثبت رویداد در جدول Outbox برای اطلاع‌رسانی به سایر ماژول‌ها (مثلاً لاگ حسابرسی یا اطلاع‌رسانی)
            DB::table('event_outbox')->insert([
                'event_id' => Str::uuid(),
                'tenant_id' => app('current_tenant_id'),
                'aggregate_type' => 'fin_vouchers',
                'aggregate_id' => $voucher->voucher_id,
                'event_type' => 'accounting.voucher.created.v1',
                'payload' => json_encode([
                    'voucher_id' => $voucher->voucher_id,
                    'reference_number' => $voucher->reference_number,
                    'total_amount' => $voucher->total_amount,
                ]),
                'status' => 1, // 1: Pending
                'retry_count' => 0,
                'created_at' => now(),
            ]);

            return $voucher;
        });
    }
}