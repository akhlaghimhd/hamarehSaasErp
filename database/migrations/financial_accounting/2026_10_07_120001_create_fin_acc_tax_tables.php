<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P2-01/02 — Tax rate config, tax transactions, Moodian log, compliance alerts.
 * Idempotent; fully aligns legacy fin_acc_tax_transactions if present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_acc_tax_rate_configs')) {
            Schema::create('fin_acc_tax_rate_configs', function (Blueprint $table) {
                $table->uuid('tax_rate_config_id')->primary();
                $table->uuid('tenant_id');
                $table->string('tax_code', 40);
                $table->string('name', 150);
                $table->decimal('rate_percent', 8, 4)->default(0);
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
                CREATE UNIQUE INDEX IF NOT EXISTS uq_fin_acc_tax_rate_period
                ON fin_acc_tax_rate_configs (tenant_id, tax_code, valid_from)
                WHERE deleted_at IS NULL
            ');
        }

        if (! Schema::hasTable('fin_acc_tax_transactions')) {
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
                $table->string('direction', 10)->default('OUTPUT');
                $table->uuid('journal_entry_id')->nullable();

                $table->timestampTz('created_at')->useCurrent();
                $table->uuid('created_by')->nullable();

                $table->index(
                    ['tenant_id', 'source_document_type', 'source_document_id'],
                    'idx_fin_acc_tax_txn_source'
                );
                $table->index(['tenant_id', 'company_id', 'transaction_date'], 'idx_fin_acc_tax_txn_co_date');
            });
        } else {
            $this->alignLegacyTaxTransactions();
        }

        DB::statement("
            DO $$ BEGIN
                ALTER TABLE fin_acc_tax_transactions
                ADD CONSTRAINT chk_fin_acc_tax_direction
                CHECK (direction IN ('OUTPUT', 'INPUT'));
            EXCEPTION WHEN duplicate_object THEN NULL;
            END $$;
        ");

        if (! Schema::hasTable('fin_acc_moodian_submissions')) {
            Schema::create('fin_acc_moodian_submissions', function (Blueprint $table) {
                $table->uuid('moodian_submission_id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('company_id');
                $table->string('source_document_type', 100);
                $table->uuid('source_document_id');
                $table->uuid('tax_transaction_id')->nullable();
                $table->string('external_ref', 100)->nullable();
                $table->string('status', 30)->default('PENDING');
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
                DO $$ BEGIN
                    ALTER TABLE fin_acc_moodian_submissions
                    ADD CONSTRAINT chk_fin_acc_moodian_status
                    CHECK (status IN ('PENDING', 'SUBMITTED', 'ACCEPTED', 'REJECTED', 'FAILED', 'CANCELLED'));
                EXCEPTION WHEN duplicate_object THEN NULL;
                END $$;
            ");
        }

        if (! Schema::hasTable('fin_acc_compliance_alerts')) {
            Schema::create('fin_acc_compliance_alerts', function (Blueprint $table) {
                $table->uuid('compliance_alert_id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('company_id')->nullable();
                $table->string('alert_code', 60);
                $table->string('severity', 20)->default('WARN');
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
        }

        foreach ([
            'fin_acc_tax_rate_configs',
            'fin_acc_tax_transactions',
            'fin_acc_moodian_submissions',
            'fin_acc_compliance_alerts',
        ] as $t) {
            if (! Schema::hasTable($t)) {
                continue;
            }
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

    private function alignLegacyTaxTransactions(): void
    {
        $cols = [
            'company_id'           => "uuid NULL",
            'source_document_type' => "varchar(100) NULL",
            'source_document_id'   => "uuid NULL",
            'tax_rate_config_id'   => "uuid NULL",
            'tax_code'             => "varchar(40) NULL",
            'taxable_amount'       => "numeric(20,4) NULL",
            'tax_rate'             => "numeric(8,4) NULL",
            'tax_amount'           => "numeric(20,4) NULL",
            'transaction_date'     => "date NULL",
            'direction'            => "varchar(10) NOT NULL DEFAULT 'OUTPUT'",
            'journal_entry_id'     => "uuid NULL",
            'created_by'           => "uuid NULL",
            'created_at'           => "timestamptz NULL DEFAULT NOW()",
        ];

        foreach ($cols as $name => $ddl) {
            if (! Schema::hasColumn('fin_acc_tax_transactions', $name)) {
                DB::statement("ALTER TABLE fin_acc_tax_transactions ADD COLUMN {$name} {$ddl}");
            }
        }

        // Ensure primary key column exists under expected name
        if (! Schema::hasColumn('fin_acc_tax_transactions', 'tax_transaction_id')) {
            DB::statement('ALTER TABLE fin_acc_tax_transactions ADD COLUMN tax_transaction_id uuid');
            DB::statement('UPDATE fin_acc_tax_transactions SET tax_transaction_id = gen_random_uuid() WHERE tax_transaction_id IS NULL');
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
            if (Schema::hasTable($t)) {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$t}");
                // Do not drop legacy tax_transactions if it pre-existed — only drop P2-only tables
                if ($t === 'fin_acc_tax_transactions') {
                    continue;
                }
                Schema::dropIfExists($t);
            }
        }
    }
};
