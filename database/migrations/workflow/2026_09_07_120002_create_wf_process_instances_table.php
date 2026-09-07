<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * L6-WF-00 – Live process instances tracking document state in workflow graph.
 * FK to wf_process_definitions is intra-bounded-context (allowed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wf_process_instances', function (Blueprint $table) {
            $table->uuid('process_instance_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('process_definition_id')->notNull();
            $table->uuid('target_aggregate_id')->notNull();
            $table->string('target_aggregate_type', 100)->notNull();
            $table->string('current_state', 100)->notNull();
            $table->uuid('owning_tenant_id')->notNull();
            $table->smallInteger('status')->notNull()->default(1); // 1 Running, 2 Completed, 3 Terminated
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('ALTER TABLE wf_process_instances ADD CONSTRAINT fk_wf_instances_definition FOREIGN KEY (process_definition_id) REFERENCES wf_process_definitions(process_definition_id) ON DELETE RESTRICT');
        DB::statement('CREATE INDEX idx_wf_instances_target ON wf_process_instances (tenant_id, target_aggregate_type, target_aggregate_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_wf_instances_owner ON wf_process_instances (owning_tenant_id, current_state) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_wf_instances_status ON wf_process_instances (tenant_id, status) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('wf_process_instances');
    }
};
