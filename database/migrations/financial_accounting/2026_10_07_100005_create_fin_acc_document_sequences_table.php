<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P0-07 — Document number sequences per tenant + company + fiscal year key
 * next_number advanced under transaction with row lock (service layer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_acc_document_sequences', function (Blueprint $table) {
            $table->uuid('sequence_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->string('sequence_key', 50); // e.g. JOURNAL, or year code
            $table->string('fiscal_year_key', 20); // e.g. 1404 or 2026
            $table->string('prefix', 30)->nullable();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedSmallInteger('pad_length')->default(6);

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'company_id'], 'idx_fin_acc_seq_company');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_fin_acc_document_sequences
            ON fin_acc_document_sequences (tenant_id, company_id, sequence_key, fiscal_year_key)
        ');

        DB::statement('ALTER TABLE fin_acc_document_sequences ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_acc_document_sequences FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_document_sequences');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON fin_acc_document_sequences
            FOR ALL
            USING (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
            WITH CHECK (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_document_sequences');
        Schema::dropIfExists('fin_acc_document_sequences');
    }
};
