<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/** L6-PS-09 – Internal purchase requisitions. department_id logical ref to Organization. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->uuid('requisition_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('department_id')->notNull();
            $table->string('requisition_number', 100)->notNull();
            $table->date('required_date')->notNull();
            $table->smallInteger('priority')->notNull()->default(2);
            $table->smallInteger('status')->notNull()->default(1);
            $table->string('description', 500)->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_purchase_requisitions_num ON purchase_requisitions (tenant_id, requisition_number) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_purchase_requisitions_dept ON purchase_requisitions (tenant_id, department_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_purchase_requisitions_status ON purchase_requisitions (tenant_id, status) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisitions');
    }
};
