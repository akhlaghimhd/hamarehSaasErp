<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair path: table may have been created empty/partial without feature_code.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_acc_smart_action_logs')) {
            Schema::create('fin_acc_smart_action_logs', function (Blueprint $table) {
                $table->uuid('smart_action_log_id')->primary();
                $table->uuid('tenant_id');
                $table->string('action_type', 40);
                $table->string('feature_code', 20);
                $table->uuid('actor_id')->nullable();
                $table->string('decision', 20)->nullable();
                $table->jsonb('payload')->nullable();
                $table->string('related_entity_type', 80)->nullable();
                $table->uuid('related_entity_id')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->index(['tenant_id', 'feature_code', 'created_at'], 'idx_fin_acc_smart_log_feat');
            });

            DB::statement('ALTER TABLE fin_acc_smart_action_logs ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE fin_acc_smart_action_logs FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_smart_action_logs');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON fin_acc_smart_action_logs
                FOR ALL
                USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            ");

            return;
        }

        Schema::table('fin_acc_smart_action_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('fin_acc_smart_action_logs', 'feature_code')) {
                $table->string('feature_code', 20)->default('K2')->after('action_type');
            }
            if (! Schema::hasColumn('fin_acc_smart_action_logs', 'actor_id')) {
                $table->uuid('actor_id')->nullable();
            }
            if (! Schema::hasColumn('fin_acc_smart_action_logs', 'decision')) {
                $table->string('decision', 20)->nullable();
            }
            if (! Schema::hasColumn('fin_acc_smart_action_logs', 'payload')) {
                $table->jsonb('payload')->nullable();
            }
            if (! Schema::hasColumn('fin_acc_smart_action_logs', 'related_entity_type')) {
                $table->string('related_entity_type', 80)->nullable();
            }
            if (! Schema::hasColumn('fin_acc_smart_action_logs', 'related_entity_id')) {
                $table->uuid('related_entity_id')->nullable();
            }
        });

        // Ensure RLS
        DB::statement('ALTER TABLE fin_acc_smart_action_logs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_acc_smart_action_logs FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_smart_action_logs');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON fin_acc_smart_action_logs
            FOR ALL
            USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        // non-destructive
    }
};
