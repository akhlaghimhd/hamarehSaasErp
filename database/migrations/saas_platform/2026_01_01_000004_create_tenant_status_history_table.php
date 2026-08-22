<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_status_history', function (Blueprint $table) {
            $table->uuid('status_history_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->notNull();
            $table->smallInteger('previous_status')->notNull();
            $table->smallInteger('new_status')->notNull();
            $table->text('reason')->nullable();
            
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_tenant_status_history_tenant ON tenant_status_history(tenant_id, created_at DESC);');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_status_history');
    }
};