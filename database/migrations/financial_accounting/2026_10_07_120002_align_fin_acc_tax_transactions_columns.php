<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align legacy fin_acc_tax_transactions to P2 service expectations.
 * Safe to re-run: only ADD COLUMN IF missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_acc_tax_transactions')) {
            return;
        }

        $cols = [
            'company_id'           => 'uuid NULL',
            'source_document_type' => 'varchar(100) NULL',
            'source_document_id'   => 'uuid NULL',
            'tax_rate_config_id'   => 'uuid NULL',
            'tax_code'             => 'varchar(40) NULL',
            'taxable_amount'       => 'numeric(20,4) NULL',
            'tax_rate'             => 'numeric(8,4) NULL',
            'tax_amount'           => 'numeric(20,4) NULL',
            'transaction_date'     => 'date NULL',
            'direction'            => "varchar(10) NOT NULL DEFAULT 'OUTPUT'",
            'journal_entry_id'     => 'uuid NULL',
            'created_by'           => 'uuid NULL',
            'created_at'           => 'timestamptz NULL DEFAULT NOW()',
            'tax_transaction_id'   => 'uuid NULL',
            'tenant_id'            => 'uuid NULL',
        ];

        foreach ($cols as $name => $ddl) {
            if (! Schema::hasColumn('fin_acc_tax_transactions', $name)) {
                DB::statement("ALTER TABLE fin_acc_tax_transactions ADD COLUMN {$name} {$ddl}");
            }
        }

        // Backfill PK if null
        if (Schema::hasColumn('fin_acc_tax_transactions', 'tax_transaction_id')) {
            DB::statement('
                UPDATE fin_acc_tax_transactions
                SET tax_transaction_id = gen_random_uuid()
                WHERE tax_transaction_id IS NULL
            ');
        }
    }

    public function down(): void
    {
        // non-destructive: leave columns
    }
};
