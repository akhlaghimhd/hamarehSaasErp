<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_contact_points', function (Blueprint $table) {
            $table->uuid('contact_point_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            $table->string('entity_type', 100); // Polymorphic Type (e.g., 'BUSINESS_PARTNER')
            $table->uuid('entity_id'); // Polymorphic Logical Reference
            
            $table->string('contact_type', 50); // e.g., 'EMAIL', 'PHONE', 'MOBILE', 'FAX'
            $table->string('contact_value', 255);
            $table->string('extension', 20)->nullable(); // برای شماره‌های داخلی
            $table->boolean('is_primary')->default(false);
            $table->smallInteger('status')->default(1);
            
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['entity_id', 'entity_type'], 'idx_contact_points_polymorphic');
            $table->index('tenant_id', 'idx_contact_points_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_contact_points');
    }
};