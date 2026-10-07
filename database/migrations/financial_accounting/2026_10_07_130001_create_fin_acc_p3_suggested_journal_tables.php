<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P3 — Account determination rules + suggested journal drafts (K1) with per-line reason + decision audit.
 * Never auto-post; accept creates a real DRAFT journal only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_acc_account_determination_rules', function (Blueprint $table) {
            $table->uuid('rule_id')->primary();
            $table->uuid('tenant_id');
            $table->string('event_type', 80); // e.g. SALES_INVOICE_POSTED
            $table->string('line_role', 40); // REVENUE | RECEIVABLE | TAX_OUTPUT | EXPENSE | PAYABLE | TAX_INPUT | CASH
            $table->uuid('account_id'); // → fin_acc_accounts
            $table->uuid('company_id')->nullable(); // null = tenant default
            $table->integer('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->string('description', 300)->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->softDeletesTz();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('account_id', 'fk_fin_acc_det_account')
                ->references('account_id')
                ->on('fin_acc_accounts')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'event_type', 'line_role'], 'idx_fin_acc_det_lookup');
        });

        Schema::create('fin_acc_suggested_journals', function (Blueprint $table) {
            $table->uuid('suggested_journal_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('ledger_id');
            $table->uuid('period_id');
            $table->string('source_event_type', 80);
            $table->uuid('source_document_id');
            $table->string('status', 20)->default('PENDING'); // PENDING | ACCEPTED | REJECTED
            $table->string('description', 500)->nullable();
            $table->uuid('journal_entry_id')->nullable(); // set on accept
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'status'], 'idx_fin_acc_sug_status');
            $table->index(['tenant_id', 'source_event_type', 'source_document_id'], 'idx_fin_acc_sug_source');
        });

        DB::statement("
            ALTER TABLE fin_acc_suggested_journals
            ADD CONSTRAINT chk_fin_acc_sug_status
            CHECK (status IN ('PENDING', 'ACCEPTED', 'REJECTED'))
        ");

        Schema::create('fin_acc_suggested_journal_lines', function (Blueprint $table) {
            $table->uuid('suggested_line_id')->primary();
            $table->uuid('suggested_journal_id');
            $table->uuid('tenant_id');
            $table->uuid('account_id');
            $table->decimal('debit_amount', 20, 4)->default(0);
            $table->decimal('credit_amount', 20, 4)->default(0);
            $table->string('line_role', 40)->nullable();
            $table->string('suggestion_reason', 500); // K1 mandatory reason
            $table->integer('sort_order')->default(0);

            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('suggested_journal_id', 'fk_fin_acc_sug_line_hdr')
                ->references('suggested_journal_id')
                ->on('fin_acc_suggested_journals')
                ->onDelete('restrict');

            $table->index(['suggested_journal_id'], 'idx_fin_acc_sug_lines');
        });

        Schema::create('fin_acc_smart_action_logs', function (Blueprint $table) {
            $table->uuid('smart_action_log_id')->primary();
            $table->uuid('tenant_id');
            $table->string('action_type', 40); // SUGGEST | ACCEPT | REJECT
            $table->string('subject_type', 80);
            $table->uuid('subject_id');
            $table->uuid('actor_id')->nullable();
            $table->text('payload_json')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'subject_type', 'subject_id'], 'idx_fin_acc_smart_log');
        });

        foreach ([
            'fin_acc_account_determination_rules',
            'fin_acc_suggested_journals',
            'fin_acc_suggested_journal_lines',
            'fin_acc_smart_action_logs',
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
            'fin_acc_smart_action_logs',
            'fin_acc_suggested_journal_lines',
            'fin_acc_suggested_journals',
            'fin_acc_account_determination_rules',
        ] as $t) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$t}");
            Schema::dropIfExists($t);
        }
    }
};
