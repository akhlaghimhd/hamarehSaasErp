<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG Sales/Purch completion SP-P0/P1/P2 partial:
 * - description + is_reference on existing masters
 * - distribution channels, product divisions
 * - sales org ↔ channel/division links
 * - sales areas (SAP-class combination)
 * - sales offices + groups
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('erp_sales_organizations') && !Schema::hasColumn('erp_sales_organizations', 'description')) {
            Schema::table('erp_sales_organizations', function (Blueprint $table) {
                $table->string('description', 500)->nullable()->after('name');
            });
        }

        if (Schema::hasTable('erp_purchasing_organizations')) {
            Schema::table('erp_purchasing_organizations', function (Blueprint $table) {
                if (!Schema::hasColumn('erp_purchasing_organizations', 'description')) {
                    $table->string('description', 500)->nullable()->after('name');
                }
                if (!Schema::hasColumn('erp_purchasing_organizations', 'is_reference')) {
                    $table->boolean('is_reference')->default(false)->after('is_active');
                }
            });
        }

        Schema::create('erp_distribution_channels', function (Blueprint $table) {
            $table->uuid('distribution_channel_id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
            $table->index(['tenant_id'], 'idx_erp_dist_channel_tenant');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_dist_channel_code
            ON erp_distribution_channels (tenant_id, code)
            WHERE deleted_at IS NULL
        ');

        Schema::create('erp_product_divisions', function (Blueprint $table) {
            $table->uuid('division_id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
            $table->index(['tenant_id'], 'idx_erp_prod_div_tenant');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_prod_div_code
            ON erp_product_divisions (tenant_id, code)
            WHERE deleted_at IS NULL
        ');

        Schema::create('erp_sales_org_channels', function (Blueprint $table) {
            $table->uuid('link_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('sales_org_id');
            $table->uuid('distribution_channel_id');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
            $table->index(['tenant_id', 'sales_org_id'], 'idx_erp_so_channel_org');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_so_channel
            ON erp_sales_org_channels (tenant_id, sales_org_id, distribution_channel_id)
            WHERE deleted_at IS NULL
        ');

        Schema::create('erp_sales_org_divisions', function (Blueprint $table) {
            $table->uuid('link_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('sales_org_id');
            $table->uuid('division_id');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
            $table->index(['tenant_id', 'sales_org_id'], 'idx_erp_so_div_org');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_so_division
            ON erp_sales_org_divisions (tenant_id, sales_org_id, division_id)
            WHERE deleted_at IS NULL
        ');

        Schema::create('erp_sales_areas', function (Blueprint $table) {
            $table->uuid('sales_area_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('sales_org_id');
            $table->uuid('distribution_channel_id');
            $table->uuid('division_id');
            $table->string('code', 80)->nullable();
            $table->string('name', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
            $table->index(['tenant_id', 'sales_org_id'], 'idx_erp_sales_area_org');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_sales_area_combo
            ON erp_sales_areas (tenant_id, sales_org_id, distribution_channel_id, division_id)
            WHERE deleted_at IS NULL
        ');

        Schema::create('erp_sales_offices', function (Blueprint $table) {
            $table->uuid('sales_office_id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->uuid('sales_org_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
            $table->index(['tenant_id'], 'idx_erp_sales_office_tenant');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_sales_office_code
            ON erp_sales_offices (tenant_id, code)
            WHERE deleted_at IS NULL
        ');

        Schema::create('erp_sales_office_areas', function (Blueprint $table) {
            $table->uuid('link_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('sales_office_id');
            $table->uuid('sales_area_id');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
            $table->index(['tenant_id', 'sales_office_id'], 'idx_erp_so_office_area');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_sales_office_area
            ON erp_sales_office_areas (tenant_id, sales_office_id, sales_area_id)
            WHERE deleted_at IS NULL
        ');

        Schema::create('erp_sales_groups', function (Blueprint $table) {
            $table->uuid('sales_group_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('sales_office_id');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
            $table->index(['tenant_id', 'sales_office_id'], 'idx_erp_sales_group_office');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_sales_group_code
            ON erp_sales_groups (tenant_id, sales_office_id, code)
            WHERE deleted_at IS NULL
        ');

        foreach ([
            'erp_distribution_channels',
            'erp_product_divisions',
            'erp_sales_org_channels',
            'erp_sales_org_divisions',
            'erp_sales_areas',
            'erp_sales_offices',
            'erp_sales_office_areas',
            'erp_sales_groups',
        ] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON {$table}
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
            'erp_sales_groups',
            'erp_sales_office_areas',
            'erp_sales_offices',
            'erp_sales_areas',
            'erp_sales_org_divisions',
            'erp_sales_org_channels',
            'erp_product_divisions',
            'erp_distribution_channels',
        ] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            Schema::dropIfExists($table);
        }

        if (Schema::hasColumn('erp_purchasing_organizations', 'is_reference')) {
            Schema::table('erp_purchasing_organizations', function (Blueprint $table) {
                $table->dropColumn('is_reference');
            });
        }
        if (Schema::hasColumn('erp_purchasing_organizations', 'description')) {
            Schema::table('erp_purchasing_organizations', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
        if (Schema::hasColumn('erp_sales_organizations', 'description')) {
            Schema::table('erp_sales_organizations', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
