<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * L5 MasterData lookup tables from 02_Master_Data_Table_Definitions.md
 * - shipment_methods
 * - payment_terms
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // shipment_methods
        // ------------------------------------------------------------------
        Schema::create('shipment_methods', function (Blueprint $table) {
            $table->uuid('shipment_method_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');

            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index('tenant_id', 'idx_shipment_methods_tenant');
        });

        DB::statement('CREATE UNIQUE INDEX uq_shipment_methods_tenant_code ON shipment_methods (tenant_id, code) WHERE deleted_at IS NULL');

        // ------------------------------------------------------------------
        // payment_terms
        // ------------------------------------------------------------------
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->uuid('payment_term_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');

            $table->string('code', 50);
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->integer('net_days')->default(0);
            $table->integer('discount_days')->default(0);
            $table->decimal('discount_percentage', 20, 4)->default(0);
            $table->decimal('penalty_percentage_per_month', 20, 4)->default(0);
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index('tenant_id', 'idx_payment_terms_tenant');
        });

        DB::statement('CREATE UNIQUE INDEX uq_payment_terms_tenant_code ON payment_terms (tenant_id, code) WHERE deleted_at IS NULL');

        // RLS
        foreach (['shipment_methods', 'payment_terms'] as $table) {
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
        foreach (['payment_terms', 'shipment_methods'] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            Schema::dropIfExists($table);
        }
    }
};
