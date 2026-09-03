<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTOs\TaxTransactionDTO;
use App\Modules\Accounting\DTOs\UpdateTaxTransactionDTO;
use App\Modules\Accounting\Models\TaxTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaxTransactionService
{
    public function getAll(): Collection
    {
        return TaxTransaction::orderByDesc('transaction_date')->get();
    }

    public function getById(string $id): TaxTransaction
    {
        return TaxTransaction::findOrFail($id);
    }

    public function recordTransaction(TaxTransactionDTO $dto): TaxTransaction
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $taxTransaction = TaxTransaction::create([
                'tenant_id'               => $tenantId,
                'transaction_date'        => $dto->transactionDate,
                'tax_type'                => $dto->taxType,
                'base_amount'             => $dto->baseAmount,
                'tax_amount'              => $dto->taxAmount,
                'tax_rate'                => $dto->taxRate,
                'reference_document_type' => $dto->referenceDocumentType,
                'reference_document_id'   => $dto->referenceDocumentId,
                'business_partner_id'     => $dto->businessPartnerId,
                'created_by'              => $userId,
                'row_version'             => 1,
            ]);

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_acc_tax_transactions',
                'aggregate_id'   => $taxTransaction->transaction_id,
                'event_type'     => 'accounting.tax_transaction.recorded.v1',
                'payload'        => json_encode([
                    'transaction_id'        => $taxTransaction->transaction_id,
                    'tax_type'              => $taxTransaction->tax_type,
                    'tax_amount'            => $taxTransaction->tax_amount,
                    'reference_document_id' => $taxTransaction->reference_document_id,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $taxTransaction;
        });
    }

    public function updateTransaction(string $id, UpdateTaxTransactionDTO $dto): TaxTransaction
    {
        return DB::transaction(function () use ($id, $dto) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $tx = TaxTransaction::findOrFail($id);

            $updateData = array_filter([
                'transaction_date'        => $dto->transactionDate,
                'tax_type'                => $dto->taxType,
                'base_amount'             => $dto->baseAmount,
                'tax_amount'              => $dto->taxAmount,
                'tax_rate'                => $dto->taxRate,
                'reference_document_type' => $dto->referenceDocumentType,
                'reference_document_id'   => $dto->referenceDocumentId,
                'business_partner_id'     => $dto->businessPartnerId,
                'updated_by'              => $userId,
            ], fn ($value) => $value !== null);

            $updateData['row_version'] = ((int) ($tx->row_version ?? 1)) + 1;

            $tx->update($updateData);

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_acc_tax_transactions',
                'aggregate_id'   => $tx->transaction_id,
                'event_type'     => 'accounting.tax_transaction.updated.v1',
                'payload'        => json_encode([
                    'transaction_id' => $tx->transaction_id,
                    'row_version'    => $tx->row_version,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $tx->fresh();
        });
    }

    public function deleteTransaction(string $id): void
    {
        DB::transaction(function () use ($id) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $tx = TaxTransaction::findOrFail($id);
            $tx->update(['deleted_by' => $userId]);
            $tx->delete();

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_acc_tax_transactions',
                'aggregate_id'   => $id,
                'event_type'     => 'accounting.tax_transaction.deleted.v1',
                'payload'        => json_encode(['transaction_id' => $id]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);
        });
    }
}
