<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * P3-D6 — partner_commissions (Database Layer 3 SSOT + ALTER extensions).
 *
 * Physical FK: partner_id, commission_rule_id (same bounded context).
 * Logical refs (no FK): tenant_id, invoice_id, currency_id.
 * currency_id + exchange_rate from SSOT ALTER section.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_commissions', function (Blueprint $table) {
            $table->uuid('commission_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('partner_id');
            $table->uuid('tenant_id'); // Logical reference
            $table->uuid('invoice_id')->nullable(); // Logical reference to platform invoice
            $table->uuid('commission_rule_id')->nullable();
            $table->decimal('base_amount', 20, 4);
            $table->smallInteger('commission_type_snapshot');
            $table->decimal('commission_value_snapshot', 20, 4);
            $table->decimal('commission_amount', 20, 4);
            $table->uuid('currency_id'); // Logical reference to currency master
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->smallInteger('status')->default(1);
            $table->timestampTz('calculated_at')->useCurrent();
            $table->timestampTz('paid_at')->nullable();

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

            $table->foreign('commission_rule_id')
                ->references('commission_rule_id')
                ->on('partner_commission_rules')
                ->onDelete('restrict');
        });

        DB::statement(
            'CREATE INDEX idx_partner_commissions_invoice ON partner_commissions (invoice_id) WHERE deleted_at IS NULL'
        );
        DB::statement(
            'CREATE INDEX idx_partner_commissions_partner ON partner_commissions (partner_id) WHERE deleted_at IS NULL'
        );
        DB::statement(
            'CREATE INDEX idx_partner_commissions_tenant ON partner_commissions (tenant_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_commissions');
    }
};
