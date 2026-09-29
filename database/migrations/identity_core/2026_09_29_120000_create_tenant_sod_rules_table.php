<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ID-W1-01 — Segregation of Duties (SoD) conflict rules.
 * Pair of roles that must not be held by the same user (within a tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_sod_rules', function (Blueprint $table) {
            $table->uuid('sod_rule_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->uuid('role_a_id')->notNull();
            $table->uuid('role_b_id')->notNull();
            $table->string('code', 100)->notNull();
            $table->string('name', 200)->notNull();
            $table->string('description', 500)->nullable();
            // 1=LOW 2=MEDIUM 3=HIGH 4=CRITICAL
            $table->smallInteger('severity')->notNull()->default(3);
            // BLOCK = reject assign; WARN = allow with warning payload
            $table->string('enforcement', 20)->notNull()->default('BLOCK');
            $table->boolean('is_active')->notNull()->default(true);
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            $table->foreign('role_a_id')->references('tenant_role_id')->on('tenant_roles')->onDelete('restrict');
            $table->foreign('role_b_id')->references('tenant_role_id')->on('tenant_roles')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_tenant_sod_rules_code ON tenant_sod_rules(tenant_id, code) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_tenant_sod_rules_pair ON tenant_sod_rules(tenant_id, role_a_id, role_b_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_tenant_sod_rules_tenant ON tenant_sod_rules(tenant_id) WHERE deleted_at IS NULL');

        DB::statement('ALTER TABLE tenant_sod_rules ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_sod_rules FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_sod_rules');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_sod_rules
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_sod_rules');
        Schema::dropIfExists('tenant_sod_rules');
    }
};
