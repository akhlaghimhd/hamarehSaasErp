<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_fiscal_periods', function (Blueprint $table) {
            $table->uuid('period_id')->primary();
            $table->uuid('tenant_id');
            
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_closed')->default(false);

            // Audit Fields
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'start_date', 'end_date'], 'idx_fin_periods_dates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_fiscal_periods');
    }
};