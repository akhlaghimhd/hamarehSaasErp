<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PLT-W1-01 — SaaS Feature Catalog
 *
 * platform_feature_catalog: global sellable pack definitions (Platform Master Data, no tenant_id)
 * tenant_feature_entitlements: which packs a tenant has purchased/enabled (tenant-scoped + RLS)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_feature_catalog', function (Blueprint $table) {
            $table->uuid('feature_id')->primary();
            $table->string('code', 80)->notNull();
            $table->string('name', 200)->notNull();
            $table->string('description', 500)->nullable();
            $table->string('category', 80)->notNull()->default('organization');
            $table->boolean('is_sellable')->notNull()->default(true);
            $table->boolean('is_active')->notNull()->default(true);
            $table->unsignedInteger('sort_order')->notNull()->default(100);
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_platform_feature_catalog_code ON platform_feature_catalog(code) WHERE deleted_at IS NULL');

        Schema::create('tenant_feature_entitlements', function (Blueprint $table) {
            $table->uuid('entitlement_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->string('feature_code', 80)->notNull();
            $table->boolean('is_enabled')->notNull()->default(true);
            // PLAN | ADDON | MANUAL | TRIAL
            $table->string('source', 30)->notNull()->default('MANUAL');
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_to')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_tenant_feature_entitlements ON tenant_feature_entitlements(tenant_id, feature_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_tenant_feature_entitlements_tenant ON tenant_feature_entitlements(tenant_id) WHERE deleted_at IS NULL');

        DB::statement('ALTER TABLE tenant_feature_entitlements ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_feature_entitlements FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_feature_entitlements');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_feature_entitlements
            FOR ALL
            USING (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
            WITH CHECK (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
        ");

        // Seed independent org feature packs (product law)
        $now = now();
        $catalog = [
            ['code' => 'multi_company', 'name' => 'چندشرکتی', 'description' => 'امکان تعریف و مدیریت بیش از یک شرکت عملیاتی', 'category' => 'organization', 'sort_order' => 10],
            ['code' => 'multi_branch', 'name' => 'چندشعبه', 'description' => 'امکان تعریف شعب فراتر از شعبه HQ پیش‌فرض', 'category' => 'organization', 'sort_order' => 20],
            ['code' => 'multi_business_unit', 'name' => 'چند واحد کسب‌وکار', 'description' => 'امکان تعریف چندین BU حتی روی یک شرکت', 'category' => 'organization', 'sort_order' => 30],
            ['code' => 'custom_org_hierarchy', 'name' => 'سلسله‌مراتب سفارشی', 'description' => 'اجازه تعریف hierarchy از نوع CUSTOM', 'category' => 'organization', 'sort_order' => 40],
        ];

        foreach ($catalog as $row) {
            DB::table('platform_feature_catalog')->insert([
                'feature_id'  => (string) Str::uuid(),
                'code'        => $row['code'],
                'name'        => $row['name'],
                'description' => $row['description'],
                'category'    => $row['category'],
                'is_sellable' => true,
                'is_active'   => true,
                'sort_order'  => $row['sort_order'],
                'created_at'  => $now,
                'updated_at'  => $now,
                'row_version' => 1,
            ]);
        }
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_feature_entitlements');
        Schema::dropIfExists('tenant_feature_entitlements');
        Schema::dropIfExists('platform_feature_catalog');
    }
};
