<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTOs\FinancialVoucherItemDTO;
use App\Modules\Accounting\Models\FinancialVoucherItem;
use App\Modules\Accounting\Models\FinancialVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FinancialVoucherItemService
{
    public function addItem(FinancialVoucherItemDTO $dto): FinancialVoucherItem
    {
        return DB::transaction(function () use ($dto) {
            // بررسی وجود سربرگ سند در کانتکست مستأجر فعلی
            $voucher = FinancialVoucher::where('voucher_id', $dto->voucherId)->first();
            
            if (!$voucher) {
                throw new NotFoundHttpException('Financial Voucher not found.');
            }

            // ثبت قلم سند
            $item = FinancialVoucherItem::create([
                'tenant_id' => app('current_tenant_id'),
                'voucher_id' => $dto->voucherId,
                'account_id' => $dto->accountId,
                'cost_center_id' => $dto->costCenterId,
                'business_partner_id' => $dto->businessPartnerId,
                'description' => $dto->description,
                'debit' => $dto->debit,
                'credit' => $dto->credit,
            ]);

            // ثبت رویداد در جدول Outbox
            DB::table('event_outbox')->insert([
                'event_id' => Str::uuid(),
                'tenant_id' => app('current_tenant_id'),
                'aggregate_type' => 'fin_voucher_items',
                'aggregate_id' => $item->item_id,
                'event_type' => 'accounting.voucher_item.added.v1',
                'payload' => json_encode([
                    'item_id' => $item->item_id,
                    'voucher_id' => $item->voucher_id,
                    'debit' => $item->debit,
                    'credit' => $item->credit,
                ]),
                'status' => 1,
                'retry_count' => 0,
                'created_at' => now(),
            ]);

            return $item;
        });
    }
}