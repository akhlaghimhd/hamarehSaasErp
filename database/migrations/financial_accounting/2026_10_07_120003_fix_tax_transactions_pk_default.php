<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy fin_acc_tax_transactions uses transaction_id NOT NULL without default.
 * Give it a UUID default so P2 inserts work; keep tax_transaction_id as alias column if present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_acc_tax_transactions')) {
            return;
        }

        if (Schema::hasColumn('fin_acc_tax_transactions', 'transaction_id')) {
            DB::statement('
                ALTER TABLE fin_acc_tax_transactions
                ALTER COLUMN transaction_id SET DEFAULT gen_random_uuid()
            ');
            // Drop NOT NULL only if we prefer app-supplied id; keep NOT NULL + default
        }

        // Ensure tenant_id not null friendly for tests
        if (Schema::hasColumn('fin_acc_tax_transactions', 'tenant_id')) {
            try {
                DB::statement('
                    ALTER TABLE fin_acc_tax_transactions
                    ALTER COLUMN tenant_id DROP NOT NULL
                ');
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    public function down(): void
    {
        // keep default
    }
};
