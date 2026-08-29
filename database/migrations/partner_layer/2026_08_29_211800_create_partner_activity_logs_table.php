<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * P3-D11 — partner_activity_logs (Database Layer 3 SSOT).
 * partner_id: physical FK within PartnerLayer.
 * user_id: logical reference to IdentityCore (no cross-module FK).
 * Append-oriented log table (no soft delete / row_version in SSOT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_activity_logs', function (Blueprint $table) {
            $table->uuid('partner_log_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('partner_id');
            $table->uuid('user_id'); // Logical reference to IdentityCore
            $table->string('action_type', 100);
            $table->text('description');
            $table->string('ip_address', 45);
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('partner_id')
                ->references('partner_id')
                ->on('partners')
                ->onDelete('restrict');
        });

        DB::statement(
            'CREATE INDEX idx_partner_activity_parent ON partner_activity_logs (partner_id, created_at DESC)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_activity_logs');
    }
};
