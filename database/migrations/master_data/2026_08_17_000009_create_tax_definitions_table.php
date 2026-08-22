<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_definitions', function (Blueprint $table) {
            $table->uuid('tax_definition_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            // کلید خارجی فیزیکی درون یک Bounded Context مجاز است
            $table->uuid('tax_category_id');
            $table->foreign('tax_category_id')
                  ->references('tax_category_id')
                  ->on('tax_categories')
                  ->onDelete('restrict');
            
            $table->string('code', 50);
            $table->string('name', 200);
            $table->smallInteger('tax_type'); // 1: VAT, 2: Sales Tax, 3: Purchase Tax, etc.
            $table->smallInteger('calculation_type')->default(1); // 1: Percentage, 2: Fixed Amount
            $table->decimal('tax_rate', 20, 4)->default(0.0000);
            $table->smallInteger('status')->default(1);
            
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->unique(['tenant_id', 'code'], 'uq_tax_definitions_tenant_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_definitions');
    }
};