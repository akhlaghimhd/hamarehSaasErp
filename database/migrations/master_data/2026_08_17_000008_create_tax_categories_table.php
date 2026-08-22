<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_categories', function (Blueprint $table) {
            $table->uuid('tax_category_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            $table->string('code', 50);
            $table->string('name', 200);
            $table->smallInteger('status')->default(1);
            
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->unique(['tenant_id', 'code'], 'uq_tax_categories_tenant_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_categories');
    }
};