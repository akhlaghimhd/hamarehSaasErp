<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTOs\TaxTransactionDTO;
use App\Modules\Accounting\Models\TaxTransaction;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaxTransactionService
{
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
}
