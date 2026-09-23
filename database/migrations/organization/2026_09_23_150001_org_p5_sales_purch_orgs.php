<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P5-01 / ORG-P5-02 / ORG-P5-03
 * Sales & Purchasing organizations + assignments to company / branch (plant-grade site)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_sales_organizations', function (Blueprint $table) {
            $table->uuid('sales_org_id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->uuid('company_id')->nullable(); // controlling company (logical)
            $table->boolean('is_active')->default(true);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id'], 'idx_erp_sales_org_tenant');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_sales_org_code
            ON erp_sales_organizations (tenant_id, code)
            WHERE deleted_at IS NULL
        ');

        Schema::create('erp_purchasing_organizations', function (Blueprint $table) {
            $table->uuid('purch_org_id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->uuid('company_id')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id'], 'idx_erp_purch_org_tenant');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_purch_org_code
            ON erp_purchasing_organizations (tenant_id, code)
            WHERE deleted_at IS NULL
        ');

        Schema::create('erp_sales_org_assignments', function (Blueprint $table) {
            $table->uuid('assignment_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('sales_org_id');
            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable(); // plant-grade site
            $table->boolean('is_active')->default(true);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'sales_org_id'], 'idx_erp_sales_asg_org');
        });

        Schema::create('erp_purch_org_assignments', function (Blueprint $table) {
            $table->uuid('assignment_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('purch_org_id');
            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'purch_org_id'], 'idx_erp_purch_asg_org');
        });

        foreach ([
            'erp_sales_organizations',
            'erp_purchasing_organizations',
            'erp_sales_org_assignments',
            'erp_purch_org_assignments',
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
            'erp_purch_org_assignments',
            'erp_sales_org_assignments',
            'erp_purchasing_organizations',
            'erp_sales_organizations',
        ] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            Schema::dropIfExists($table);
        }
    }
};
