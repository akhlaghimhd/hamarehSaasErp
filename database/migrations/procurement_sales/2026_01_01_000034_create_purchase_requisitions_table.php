<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->uuid('requisition_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول Tenant Management
            $table->string('requisition_number', 100)->notNull();
            $table->uuid('department_id')->notNull(); // Logical reference to Department
            $table->uuid('requester_user_id')->notNull(); // Logical reference to Identity User
            $table->date('requisition_date')->notNull()->default(DB::raw('CURRENT_DATE'));
            $table->date('required_date')->notNull();
            $table->smallInteger('status')->notNull()->default(1); // 1: Draft, 2: Pending Approval, 3: Approved, 0: Rejected
            $table->text('description')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants (خارج از ماژول) حذف شد.
        });

        DB::statement('CREATE UNIQUE INDEX uq_purchase_requisitions_number ON purchase_requisitions(tenant_id, requisition_number) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_purchase_requisitions_status ON purchase_requisitions(tenant_id, status) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisitions');
    }
};