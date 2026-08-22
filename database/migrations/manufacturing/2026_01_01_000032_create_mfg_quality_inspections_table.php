<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_quality_inspections', function (Blueprint $table) {
            $table->uuid('inspection_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول Tenant Management
            $table->smallInteger('inspection_type')->notNull(); // 1: Incoming Material, 2: Production Output, 3: Final Product
            $table->string('source_document_type', 100)->nullable();
            $table->uuid('source_document_id')->nullable(); // ارجاع منطقی پویا
            $table->uuid('item_id')->notNull(); // ارجاع منطقی به Inventory Item
            $table->uuid('batch_id')->nullable(); // ارجاع منطقی به Stock Batches
            $table->string('inspection_number', 100)->notNull();
            $table->timestampTz('inspection_date')->notNull()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('inspector_user_id')->notNull(); // ارجاع منطقی به Identity Core User
            $table->decimal('sample_quantity', 20, 4)->notNull()->default(0.0000);
            $table->decimal('accepted_quantity', 20, 4)->notNull()->default(0.0000);
            $table->decimal('rejected_quantity', 20, 4)->notNull()->default(0.0000);
            $table->smallInteger('qc_status')->notNull()->default(1); // 1: Pending, 2: Approved, 3: Rejected, 4: Quarantine
            $table->text('notes')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants (خارج از ماژول) حذف شد.
        });

        DB::statement('CREATE UNIQUE INDEX uq_mfg_qc_number ON mfg_quality_inspections(tenant_id, inspection_number) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_mfg_qc_status ON mfg_quality_inspections(tenant_id, qc_status) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_quality_inspections');
    }
};