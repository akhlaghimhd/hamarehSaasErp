<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_branches', function (Blueprint $table) {
            $table->uuid('branch_id')->primary();
            $table->uuid('tenant_id');
            
            // Physical FK: Allowed because it's within the SAME Bounded Context (Organization)
            $table->foreignUuid('company_id')->references('company_id')->on('erp_companies')->onDelete('restrict');
            
            $table->string('code', 50);
            $table->string('name', 200);
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);

            // Audit Fields
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index('company_id', 'idx_erp_branches_company');
        });

        DB::statement('CREATE UNIQUE INDEX uq_erp_branches_code ON erp_branches(tenant_id, company_id, code) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_branches');
    }
};