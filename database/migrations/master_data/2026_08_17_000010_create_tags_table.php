<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('tag_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            $table->string('scope_type', 50); // SYSTEM, MODULE, ENTITY
            $table->string('module_code', 50)->nullable(); // INV, PUR, SAL, FIN, HR
            $table->string('entity_type', 100)->nullable(); // INVOICE, ITEM, CUSTOMER
            $table->string('tag_name', 100);
            $table->string('description', 500)->nullable();
            
            // فیلدهای حسابرسی بر اساس استاندارد سیستم
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index('module_code', 'idx_tags_module_code');
            $table->index('entity_type', 'idx_tags_entity_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};