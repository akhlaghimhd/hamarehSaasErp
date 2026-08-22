<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_addresses', function (Blueprint $table) {
            $table->uuid('entity_address_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            $table->string('entity_type', 100); // Polymorphic Type (e.g., 'BUSINESS_PARTNER')
            $table->uuid('entity_id'); // Polymorphic ID (Logical Reference)
            
            $table->uuid('address_type_id'); // Logical Ref
            $table->uuid('country_id'); // Logical Ref to Global Catalog
            $table->uuid('province_id')->nullable(); // اعمال تغییرات مربوط به شناسه ساختاریافته ADD
            $table->uuid('city_id')->nullable();     // اعمال تغییرات مربوط به شناسه ساختاریافته ADD
            
            $table->string('postal_code', 50)->nullable();
            $table->text('address_text');
            $table->boolean('is_primary')->default(false);
            $table->smallInteger('status')->default(1);
            
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['entity_id', 'entity_type'], 'idx_entity_addresses_polymorphic');
            $table->index('tenant_id', 'idx_entity_addresses_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_addresses');
    }
};