<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P2-01/02 — Tax rate config (no hardcoded %), tax transactions ledger, Moodian submission log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_acc_tax_rate_configs', function (Blueprint $table) {
            $table->uuid('tax_rate_config_id')->primary();
            $table->uuid('tenant_id');
            $table->string('tax_code', 40); // VAT_STD, VAT_EXEMPT, …
            $table->string('name', 150);
            $table->decimal('rate_percent', 8, 4)->default(0); // e.g. 10.0000
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_default')->default(false);
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'tax_code'], 'idx_fin_acc_tax_rate_code');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_fin_acc_tax_rate_period
            ON fin_acc_tax_rate_configs (tenant_id, tax_code, valid_from)
            WHERE deleted_at IS NULL
        ');

        Schema::create('fin_acc_tax_transactions', function (Blueprint $table) {
            $table->uuid('tax_transaction_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->string('source_document_type', 100);
            $table->uuid('source_document_id');
            $table->uuid('tax_rate_config_id')->nullable();
            $table->string('tax_code', 40);
            $table->decimal('taxable_amount', 20, 4);
            $table->decimal('tax_rate', 8, 4);
            $table->decimal('tax_amount', 20, 4);
            $table->date('transaction_date');
            $table->string('direction', 10)->default('OUTPUT'); // OUTPUT | INPUT
            $table->uuid('journal_entry_id')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();

            $table->index(
                ['tenant_id', 'source_document_type', 'source_document_id'],
                'idx_fin_acc_tax_txn_source'
            );
            $table->index(['tenant_id', 'company_id', 'transaction_date'], 'idx_fin_acc_tax_txn_co_date');
        });

        DB::statement("
            ALTER TABLE fin_acc_tax_transactions
            ADD CONSTRAINT chk_fin_acc_tax_direction
            CHECK (direction IN ('OUTPUT', 'INPUT'))
        ");

        Schema::create('fin_acc_moodian_submissions', function (Blueprint $table) {
            $table->uuid('moodian_submission_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->string('source_document_type', 100);
            $table->uuid('source_document_id');
            $table->uuid('tax_transaction_id')->nullable();
            $table->string('external_ref', 100)->nullable(); // Moodian uid / tracking
            $table->string('status', 30)->default('PENDING');
            // PENDING | SUBMITTED | ACCEPTED | REJECTED | FAILED | CANCELLED
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('last_polled_at')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'status'], 'idx_fin_acc_moodian_status');
            $table->index(
                ['tenant_id', 'source_document_type', 'source_document_id'],
                'idx_fin_acc_moodian_source'
            );
        });

        DB::statement("
            ALTER TABLE fin_acc_moodian_submissions
            ADD CONSTRAINT chk_fin_acc_moodian_status
            CHECK (status IN ('PENDING', 'SUBMITTED', 'ACCEPTED', 'REJECTED', 'FAILED', 'CANCELLED'))
        ");

        Schema::create('fin_acc_compliance_alerts', function (Blueprint $table) {
            $table->uuid('compliance_alert_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id')->nullable();
            $table->string('alert_code', 60); // MOODIAN_MISSING, TAX_RATE_GAP, …
            $table->string('severity', 20)->default('WARN'); // INFO | WARN | BLOCK
            $table->string('title', 200);
            $table->text('message');
            $table->string('related_type', 100)->nullable();
            $table->uuid('related_id')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestampTz('resolved_at')->nullable();
            $table->uuid('resolved_by')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();

            $table->index(['tenant_id', 'is_resolved', 'severity'], 'idx_fin_acc_alerts_open');
        });

        foreach ([
            'fin_acc_tax_rate_configs',
            'fin_acc_tax_transactions',
            'fin_acc_moodian_submissions',
            'fin_acc_compliance_alerts',
        ] as $t) {
            DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$t}");
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON {$t}
                FOR ALL
                USING (
                    tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
                WITH CHECK (
                    tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
            ");
        }
    }

    public function down(): void
    {
        foreach ([
            'fin_acc_compliance_alerts',
            'fin_acc_moodian_submissions',
            'fin_acc_tax_transactions',
            'fin_acc_tax_rate_configs',
        ] as $t) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$t}");
            Schema::dropIfExists($t);
        }
    }
};
