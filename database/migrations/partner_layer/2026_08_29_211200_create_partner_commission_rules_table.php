<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * P3-D5 — partner_commission_rules (Database Layer 3 SSOT).
 * agreement_id: physical FK within PartnerLayer bounded context.
 * Amounts use NUMERIC(20,4) per SSOT precision requirement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_commission_rules', function (Blueprint $table) {
            $table->uuid('commission_rule_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('agreement_id');
            $table->smallInteger('revenue_type');
            $table->smallInteger('commission_type');
            $table->decimal('commission_value', 20, 4);
            $table->smallInteger('calculation_basis')->default(1);
            $table->decimal('minimum_amount', 20, 4)->nullable();
            $table->decimal('maximum_amount', 20, 4)->nullable();
            $table->timestampTz('effective_from')->useCurrent();
            $table->timestampTz('effective_to')->nullable();
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('agreement_id')
                ->references('agreement_id')
                ->on('partner_agreements')
                ->onDelete('restrict');
        });

        DB::statement(
            'CREATE INDEX idx_partner_comm_rules_agreement ON partner_commission_rules (agreement_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_commission_rules');
    }
};
