<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('project_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            $table->string('project_code', 50);
            $table->string('name', 200);
            $table->text('description')->nullable();
            
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            
            $table->smallInteger('status')->default(1); // 1: Planning, 2: Active, 3: On Hold, 4: Completed, 0: Cancelled
            
            // استفاده از decimal به جای numeric جهت رعایت دقت‌های عددی استاندارد NUMERIC(20,4)
            $table->decimal('budget', 20, 4)->default(0);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->unique(['tenant_id', 'project_code'], 'uq_projects_tenant_code');
            $table->index(['tenant_id', 'status'], 'idx_projects_status_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};