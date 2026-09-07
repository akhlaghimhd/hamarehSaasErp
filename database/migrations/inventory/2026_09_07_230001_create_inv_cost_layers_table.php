<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * L6-INV-18 — Perpetual cost layers for FIFO / Moving Average valuation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_cost_layers', function (Blueprint $table) {
            $table->uuid('cost_layer_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('item_id')->index();
            $table->uuid('location_id')->index();
            $table->decimal('quantity_remaining', 20, 4)->notNull()->default(0);
            $table->decimal('unit_cost', 20, 4)->notNull()->default(0);
            $table->timestampTz('received_at')->notNull()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('source_document_id')->nullable();
            $table->uuid('source_document_item_id')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE INDEX idx_inv_cost_layers_fifo ON inv_cost_layers(tenant_id, item_id, location_id, received_at) WHERE deleted_at IS NULL AND quantity_remaining > 0;');

        DB::statement('ALTER TABLE inv_cost_layers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE inv_cost_layers FORCE ROW LEVEL SECURITY');
        DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON inv_cost_layers");
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON inv_cost_layers
            FOR ALL
            USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_cost_layers');
    }
};
