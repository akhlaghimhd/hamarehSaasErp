<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * P3-D4 — partner_agreements (Database Layer 3 SSOT).
 * partner_id: physical FK within PartnerLayer bounded context.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_agreements', function (Blueprint $table) {
            $table->uuid('agreement_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('partner_id');
            $table->string('agreement_number', 100);
            $table->smallInteger('agreement_type')->default(1);
            $table->timestampTz('start_date');
            $table->timestampTz('end_date')->nullable();
            $table->smallInteger('payment_cycle')->default(1);
            $table->string('description', 500)->nullable();
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
            'CREATE UNIQUE INDEX uq_partner_agreements_number ON partner_agreements (agreement_number) WHERE deleted_at IS NULL'
        );
        DB::statement(
            'CREATE INDEX idx_partner_agreements_partner ON partner_agreements (partner_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_agreements');
    }
};
