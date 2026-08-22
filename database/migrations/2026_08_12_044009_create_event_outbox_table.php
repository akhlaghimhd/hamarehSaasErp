<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_outbox', function (Blueprint $table) {
            // استفاده از event_id به عنوان کلید اصلی طبق سند
            $table->uuid('event_id')->primary();
            
            // ایزوله‌سازی چند مستأجری
            $table->uuid('tenant_id')->index();
            
            $table->string('aggregate_type', 100);
            $table->uuid('aggregate_id');
            $table->string('event_type');
            $table->jsonb('payload');
            
            $table->smallInteger('status')->default(1); // 1: Pending, 2: Processed, 3: Failed
            $table->integer('retry_count')->default(0);
            $table->text('error_log')->nullable();
            
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();

            // ایندکس ترکیبی با قید وضعیت جهت سرعت بالای پولینگ ورکرها
            $table->index(['status', 'created_at'], 'idx_event_outbox_polling');
        });

        // اضافه کردن GIN Index روی بدنه JSONB (مخصوص PostgreSQL)
        DB::statement('CREATE INDEX idx_event_outbox_payload ON event_outbox USING gin (payload)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_outbox');
    }
};