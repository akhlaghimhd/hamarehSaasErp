<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * L6-PS-08 – Payment schedules for AR/AP settlement against official invoices.
 * Logical refs: source_document_id (SAL_INVOICE / PUR_INVOICE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_payment_schedules', function (Blueprint $table) {
            $table->uuid('payment_schedule_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->string('source_document_type', 50)->notNull();
            $table->uuid('source_document_id')->notNull();
            $table->date('due_date')->notNull();
            $table->decimal('expected_amount', 20, 4)->notNull();
            $table->decimal('paid_amount', 20, 4)->notNull()->default(0.0000);
            $table->smallInteger('status')->notNull()->default(1); // 1 Pending, 2 Partial, 3 Settled, 4 Overdue
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE INDEX idx_fin_schedules_lookup ON fin_payment_schedules (source_document_type, source_document_id)');
        DB::statement('CREATE INDEX idx_fin_schedules_due ON fin_payment_schedules (tenant_id, due_date) WHERE status IN (1, 2) AND deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_fin_schedules_tenant_status ON fin_payment_schedules (tenant_id, status) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_payment_schedules');
    }
};
