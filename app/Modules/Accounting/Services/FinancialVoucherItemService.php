<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTOs\FinancialVoucherItemDTO;
use App\Modules\Accounting\DTOs\UpdateFinancialVoucherItemDTO;
use App\Modules\Accounting\Models\FinancialVoucherItem;
use App\Modules\Accounting\Models\FinancialVoucher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FinancialVoucherItemService
{
    public function getAll(?string $voucherId = null): Collection
    {
        $query = FinancialVoucherItem::query()->orderBy('created_at');

        if ($voucherId) {
            $query->where('voucher_id', $voucherId);
        }

        return $query->get();
    }

    public function getById(string $id): FinancialVoucherItem
    {
        return FinancialVoucherItem::findOrFail($id);
    }

    public function addItem(FinancialVoucherItemDTO $dto): FinancialVoucherItem
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $voucher = FinancialVoucher::where('voucher_id', $dto->voucherId)->first();

            if (!$voucher) {
                throw new NotFoundHttpException('Financial Voucher not found.');
            }

            if ((int) $voucher->status !== FinancialVoucherService::STATUS_DRAFT) {
                throw new ConflictHttpException('Items can only be added to draft vouchers.');
            }

            $item = FinancialVoucherItem::create([
                'tenant_id'           => $tenantId,
                'voucher_id'          => $dto->voucherId,
                'account_id'          => $dto->accountId,
                'cost_center_id'      => $dto->costCenterId,
                'business_partner_id' => $dto->businessPartnerId,
                'description'         => $dto->description,
                'debit'               => $dto->debit,
                'credit'              => $dto->credit,
                'created_by'          => $userId,
                'row_version'         => 1,
            ]);

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_voucher_items',
                'aggregate_id'   => $item->item_id,
                'event_type'     => 'accounting.voucher_item.added.v1',
                'payload'        => json_encode([
                    'item_id'    => $item->item_id,
                    'voucher_id' => $item->voucher_id,
                    'debit'      => $item->debit,
                    'credit'     => $item->credit,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $item;
        });
    }

    public function updateItem(string $id, UpdateFinancialVoucherItemDTO $dto): FinancialVoucherItem
    {
        return DB::transaction(function () use ($id, $dto) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $item = FinancialVoucherItem::findOrFail($id);
            $this->assertParentIsDraft($item->voucher_id);

            $updateData = array_filter([
                'account_id'          => $dto->accountId,
                'cost_center_id'      => $dto->costCenterId,
                'business_partner_id' => $dto->businessPartnerId,
                'description'         => $dto->description,
                'debit'               => $dto->debit,
                'credit'              => $dto->credit,
                'updated_by'          => $userId,
            ], fn ($value) => $value !== null);

            $updateData['row_version'] = ((int) ($item->row_version ?? 1)) + 1;

            $item->update($updateData);

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_voucher_items',
                'aggregate_id'   => $item->item_id,
                'event_type'     => 'accounting.voucher_item.updated.v1',
                'payload'        => json_encode([
                    'item_id'     => $item->item_id,
                    'voucher_id'  => $item->voucher_id,
                    'row_version' => $item->row_version,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $item->fresh();
        });
    }

    public function deleteItem(string $id): void
    {
        DB::transaction(function () use ($id) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $item = FinancialVoucherItem::findOrFail($id);
            $this->assertParentIsDraft($item->voucher_id);

            $item->update(['deleted_by' => $userId]);
            $item->delete();

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_voucher_items',
                'aggregate_id'   => $id,
                'event_type'     => 'accounting.voucher_item.deleted.v1',
                'payload'        => json_encode([
                    'item_id'    => $id,
                    'voucher_id' => $item->voucher_id,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);
        });
    }

    protected function assertParentIsDraft(string $voucherId): void
    {
        $voucher = FinancialVoucher::where('voucher_id', $voucherId)->first();

        if (!$voucher) {
            throw new NotFoundHttpException('Financial Voucher not found.');
        }

        if ((int) $voucher->status !== FinancialVoucherService::STATUS_DRAFT) {
            throw new ConflictHttpException('Items can only be modified on draft vouchers.');
        }
    }
}
