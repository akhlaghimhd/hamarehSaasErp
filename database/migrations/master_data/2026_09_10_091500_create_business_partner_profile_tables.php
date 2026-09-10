<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Layer 5 MasterData – missing Business Partner profile tables
 * Source of truth: 02_Master_Data_Table_Definitions.md
 * Compliance: tenant_id, SoftDeletes, full audit, partial unique, RLS
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // 1. persons (Individual profile – 1:1 with business_partners)
        // ------------------------------------------------------------------
        Schema::create('persons', function (Blueprint $table) {
            $table->uuid('person_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('business_partner_id');

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('national_code', 50)->nullable();
            $table->date('birth_date')->nullable();
            $table->smallInteger('gender')->nullable();
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index('tenant_id', 'idx_persons_tenant');
            $table->index('national_code', 'idx_persons_national_code');
        });

        // Physical FK allowed (same Bounded Context)
        Schema::table('persons', function (Blueprint $table) {
            $table->foreign('business_partner_id')
                ->references('business_partner_id')
                ->on('business_partners')
                ->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_persons_business_partner ON persons (business_partner_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_persons_tenant_active ON persons (tenant_id) WHERE deleted_at IS NULL');

        // ------------------------------------------------------------------
        // 2. business_partner_organizations (Organization profile – 1:1)
        // ------------------------------------------------------------------
        Schema::create('business_partner_organizations', function (Blueprint $table) {
            $table->uuid('business_partner_organization_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('business_partner_id');

            $table->string('legal_name', 200);
            $table->string('trade_name', 200)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->smallInteger('organization_type')->nullable();
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index('tenant_id', 'idx_bp_organizations_tenant');
        });

        Schema::table('business_partner_organizations', function (Blueprint $table) {
            $table->foreign('business_partner_id')
                ->references('business_partner_id')
                ->on('business_partners')
                ->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_bp_organizations_business_partner ON business_partner_organizations (business_partner_id) WHERE deleted_at IS NULL');

        // ------------------------------------------------------------------
        // 3. business_partner_roles
        // ------------------------------------------------------------------
        Schema::create('business_partner_roles', function (Blueprint $table) {
            $table->uuid('business_partner_role_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('business_partner_id');

            $table->smallInteger('role_type'); // 1 Customer … 10 Other
            $table->string('role_code', 50);
            $table->timestampTz('effective_from')->useCurrent();
            $table->timestampTz('effective_to')->nullable();
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index('tenant_id', 'idx_bp_roles_tenant');
            $table->index('role_type', 'idx_bp_roles_type');
        });

        Schema::table('business_partner_roles', function (Blueprint $table) {
            $table->foreign('business_partner_id')
                ->references('business_partner_id')
                ->on('business_partners')
                ->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_business_partner_roles_active ON business_partner_roles (business_partner_id, role_type) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_business_partner_role_code ON business_partner_roles (business_partner_id, role_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_business_partner_roles_partner ON business_partner_roles (business_partner_id) WHERE deleted_at IS NULL');

        // ------------------------------------------------------------------
        // 4. business_partner_contacts
        // ------------------------------------------------------------------
        Schema::create('business_partner_contacts', function (Blueprint $table) {
            $table->uuid('business_partner_contact_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('business_partner_id');

            $table->smallInteger('contact_type'); // 1 Primary … 6 Legal
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('job_title', 150)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index('tenant_id', 'idx_bp_contacts_tenant');
            $table->index('email', 'idx_bp_contacts_email');
        });

        Schema::table('business_partner_contacts', function (Blueprint $table) {
            $table->foreign('business_partner_id')
                ->references('business_partner_id')
                ->on('business_partners')
                ->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_bp_contacts_partner ON business_partner_contacts (business_partner_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_bp_primary_contact ON business_partner_contacts (business_partner_id, contact_type) WHERE is_primary = TRUE AND deleted_at IS NULL');

        // ------------------------------------------------------------------
        // 5. business_partner_identifications
        // ------------------------------------------------------------------
        Schema::create('business_partner_identifications', function (Blueprint $table) {
            $table->uuid('bp_identification_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('business_partner_id');

            $table->string('identification_type_code', 50); // ECONOMIC_CODE, PASSPORT, …
            $table->string('id_number', 100);
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index('tenant_id', 'idx_bp_identifications_tenant');
        });

        Schema::table('business_partner_identifications', function (Blueprint $table) {
            $table->foreign('business_partner_id')
                ->references('business_partner_id')
                ->on('business_partners')
                ->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_bp_identifications_tenant ON business_partner_identifications (tenant_id, identification_type_code, id_number) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_bp_identifications_partner ON business_partner_identifications (business_partner_id) WHERE deleted_at IS NULL');

        // ------------------------------------------------------------------
        // RLS for all new tables
        // ------------------------------------------------------------------
        $tables = [
            'persons',
            'business_partner_organizations',
            'business_partner_roles',
            'business_partner_contacts',
            'business_partner_identifications',
        ];

        foreach ($tables as $table) {
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
        $tables = [
            'business_partner_identifications',
            'business_partner_contacts',
            'business_partner_roles',
            'business_partner_organizations',
            'persons',
        ];

        foreach ($tables as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            Schema::dropIfExists($table);
        }
    }
};
