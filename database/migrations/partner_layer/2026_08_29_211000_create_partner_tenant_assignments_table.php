<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * P3-D3 — partner_tenant_assignments (Database Layer 3 SSOT).
 *
 * partner_id: physical FK within PartnerLayer bounded context.
 * tenant_id: logical reference only (no FK to tenants — Law 2.2 / 2.3).
 * assigned_by: logical reference to acting user/admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_tenant_assignments', function (Blueprint $table) {
            $table->uuid('assignment_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('partner_id');
            $table->uuid('tenant_id'); // Logical reference — no physical FK across modules
            $table->smallInteger('assignment_type')->default(1);
            $table->timestampTz('start_date')->useCurrent();
            $table->timestampTz('end_date')->nullable();
            $table->string('transfer_reason', 500)->nullable();
            $table->uuid('assigned_by')->nullable();
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('partner_id')
                ->references('partner_id')
                ->on('partners')
                ->onDelete('restrict');
        });

        DB::statement(
            'CREATE INDEX idx_partner_assignments_tenant ON partner_tenant_assignments (tenant_id) WHERE deleted_at IS NULL'
        );
        DB::statement(
            'CREATE INDEX idx_partner_assignments_partner ON partner_tenant_assignments (partner_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_tenant_assignments');
    }
};
