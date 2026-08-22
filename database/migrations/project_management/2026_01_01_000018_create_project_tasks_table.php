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
        // ۱. ساخت اولیه جدول فعالیت‌های پروژه (project_tasks)
        Schema::create('project_tasks', function (Blueprint $table) {
            // حتماً ->primary() باید روی task_id وجود داشته باشد
            $table->uuid('task_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('project_id');
            $table->uuid('parent_task_id')->nullable();
            
            $table->string('task_code', 50);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->smallInteger('status')->default(1);
            $table->smallInteger('priority')->default(2);
            
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('actual_end_date')->nullable();
            
            // استفاده از decimal جهت رعایت دقت‌های عددی استاندارد NUMERIC(20,4)
            $table->decimal('estimated_hours', 20, 4)->default(0);
            $table->decimal('actual_hours', 20, 4)->default(0);
            
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            // کلید خارجی پروژه و ایندکس‌ها
            $table->foreign('project_id')
                  ->references('project_id')
                  ->on('projects')
                  ->onDelete('restrict');

            $table->unique(['project_id', 'task_code'], 'uq_project_tasks_project_code');
            $table->index('tenant_id', 'idx_project_tasks_tenant');
            $table->index('project_id', 'idx_project_tasks_project');
            $table->index('parent_task_id', 'idx_project_tasks_parent');
        });

        // ۲. افزودن کلید خارجی Self-Referencing پس از تعریف کامل کلید اصلی task_id
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->foreign('parent_task_id')
                  ->references('task_id')
                  ->on('project_tasks')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};