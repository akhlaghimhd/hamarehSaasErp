<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_members', function (Blueprint $table) {
            $table->uuid('project_member_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول Tenant Management
            $table->uuid('project_id')->notNull(); // درون ماژولی
            $table->uuid('employee_id')->notNull(); // ارجاع منطقی به ماژول HR
            $table->string('project_role', 100)->notNull(); // e.g. PROJECT_MANAGER, LEAD_DEVELOPER, QC_ENGINEER
            $table->date('joined_at')->notNull()->default(DB::raw('CURRENT_DATE'));
            $table->date('left_at')->nullable();
            $table->boolean('is_active')->notNull()->default(true);

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants حذف شد. فقط کلید درون‌ماژولی باقی می‌ماند:
            $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_project_members_unique ON project_members(project_id, employee_id) WHERE is_active = TRUE AND deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_project_members_employee ON project_members(tenant_id, employee_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};