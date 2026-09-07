<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * L6-WF-00 – Polymorphic worklist (ADD-04).
 * assigned_type 1=Internal Role, 2=External Business Partner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wf_tasks', function (Blueprint $table) {
            $table->uuid('task_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('process_instance_id')->notNull();
            $table->smallInteger('assigned_type')->notNull(); // 1 Internal Role, 2 External BP
            $table->uuid('assigned_to_id')->notNull();
            $table->string('task_name', 200)->notNull();
            $table->smallInteger('status')->notNull()->default(1); // 1 Pending, 2 Approved, 3 Rejected
            $table->jsonb('context_snapshots')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('actioned_at')->nullable();
            $table->uuid('actioned_by')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('ALTER TABLE wf_tasks ADD CONSTRAINT fk_wf_tasks_instance FOREIGN KEY (process_instance_id) REFERENCES wf_process_instances(process_instance_id) ON DELETE RESTRICT');
        DB::statement('CREATE INDEX idx_wf_tasks_assignment ON wf_tasks (tenant_id, assigned_type, assigned_to_id) WHERE status = 1 AND deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_wf_tasks_instance ON wf_tasks (tenant_id, process_instance_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('wf_tasks');
    }
};
