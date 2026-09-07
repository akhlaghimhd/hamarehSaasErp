<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * L6-WF-00 – Process definition templates (ADD-04 / Master Data Table Definitions).
 * flow_graph JSONB holds dynamic state machine steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wf_process_definitions', function (Blueprint $table) {
            $table->uuid('process_definition_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->string('code', 100)->notNull();
            $table->string('name', 200)->notNull();
            $table->string('target_aggregate_type', 100)->notNull();
            $table->jsonb('flow_graph')->notNull();
            $table->boolean('is_active')->notNull()->default(true);
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_wf_process_def ON wf_process_definitions (tenant_id, code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_wf_process_def_target ON wf_process_definitions (tenant_id, target_aggregate_type) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('wf_process_definitions');
    }
};
