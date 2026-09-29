<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ID-W2-01 — Access Certification / Access Review campaigns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_access_cert_campaigns', function (Blueprint $table) {
            $table->uuid('campaign_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->string('code', 80)->notNull();
            $table->string('name', 200)->notNull();
            $table->string('description', 500)->nullable();
            // DRAFT | OPEN | COMPLETED | CANCELLED
            $table->string('status', 20)->notNull()->default('DRAFT');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->uuid('owner_user_id')->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_tenant_access_cert_campaigns_code ON tenant_access_cert_campaigns(tenant_id, code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_tenant_access_cert_campaigns_tenant ON tenant_access_cert_campaigns(tenant_id) WHERE deleted_at IS NULL');

        DB::statement('ALTER TABLE tenant_access_cert_campaigns ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_access_cert_campaigns FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_access_cert_campaigns');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_access_cert_campaigns
            FOR ALL
            USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
        ");

        Schema::create('tenant_access_cert_items', function (Blueprint $table) {
            $table->uuid('item_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->uuid('campaign_id')->notNull();
            $table->uuid('tenant_user_id')->notNull();
            $table->uuid('user_id')->notNull();
            // snapshot of role ids at open time (json array of uuids)
            $table->jsonb('role_ids_snapshot')->nullable();
            // PENDING | APPROVED | REVOKE_REQUESTED | DEFERRED
            $table->string('decision', 30)->notNull()->default('PENDING');
            $table->uuid('reviewer_user_id')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            $table->foreign('campaign_id')
                ->references('campaign_id')
                ->on('tenant_access_cert_campaigns')
                ->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_tenant_access_cert_items_member ON tenant_access_cert_items(campaign_id, tenant_user_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_tenant_access_cert_items_campaign ON tenant_access_cert_items(tenant_id, campaign_id) WHERE deleted_at IS NULL');

        DB::statement('ALTER TABLE tenant_access_cert_items ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_access_cert_items FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_access_cert_items');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_access_cert_items
            FOR ALL
            USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_access_cert_items');
        Schema::dropIfExists('tenant_access_cert_items');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_access_cert_campaigns');
        Schema::dropIfExists('tenant_access_cert_campaigns');
    }
};
